<?php

namespace App\Services;

use App\Exceptions\SaleException;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Invoice;
use App\Models\Pet;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly CashSessionService $cash,
        private readonly DailySalesSummaryService $summaries,
        private readonly BillingService $billing,
    ) {
    }

    public function paginate(User $user, ?Request $request = null): LengthAwarePaginator
    {
        $request ??= request();
        $q = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        return Sale::query()
            ->with(['user', 'items', 'payments', 'customer', 'pet'])
            ->when(
                ! $this->seesAll($user) && ! $this->sharedPending($request),
                fn ($builder) => $builder->where('user_id', $user->id)
            )
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('series', 'like', '%'.$q.'%')
                        ->orWhere('number', 'like', '%'.$q.'%')
                        ->orWhereRaw("CONCAT(series, '-', LPAD(number, 6, '0')) LIKE ?", ['%'.$q.'%'])
                        ->orWhereHas('customer', function ($customer) use ($q) {
                            $customer->whereHas('persona', function ($persona) use ($q) {
                                    $persona->where('nombres', 'like', '%'.$q.'%')
                                        ->orWhere('apellidos', 'like', '%'.$q.'%')
                                        ->orWhere('numero_documento', 'like', '%'.$q.'%')
                                        ->orWhere('telefono', 'like', '%'.$q.'%');
                                });
                        })
                        ->orWhereHas('user', fn ($cashier) => $cashier->where('name', 'like', '%'.$q.'%'));
                });
            })
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->status))
            ->when($request->filled('payment_status'), fn ($builder) => $builder->where('payment_status', $request->payment_status))
            ->when($request->filled('fecha'), fn ($builder) => $builder->whereDate('created_at', $request->fecha))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function catalog(Request $request): LengthAwarePaginator
    {
        $q = trim((string) $request->query('q', $request->query('nombre', '')));
        $perPage = (int) $request->query('per_page', 12);
        $perPage = $perPage > 0 ? min($perPage, 48) : 12;

        $page = Variante::query()
            ->with(['producto.categoria', 'batches'])
            ->where('activo', true)
            ->whereHas('producto', fn ($producto) => $producto->where('activo', true))
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('sku', 'like', '%'.$q.'%')
                        ->orWhere('barcode', $q)
                        ->orWhere('presentation', 'like', '%'.$q.'%')
                        ->orWhereHas('producto', function ($producto) use ($q) {
                            $producto->where('nombre', 'like', '%'.$q.'%')
                                ->orWhere('codigo', 'like', '%'.$q.'%')
                                ->orWhere('presentacion', 'like', '%'.$q.'%')
                                ->orWhere('talla', 'like', '%'.$q.'%');
                        });
                });
            })
            ->when($request->filled('category_id'), function ($builder) use ($request) {
                $ids = Categoria::idsWithDescendants((int) $request->query('category_id'));
                $builder->whereHas('producto', fn ($producto) => $producto->whereIn('categoria_id', $ids));
            })
            ->orderBy('id')
            ->paginate($perPage);

        $page->setCollection(
            $page->getCollection()->map(function (Variante $variante) {
                $producto = $variante->producto;
                $onHand = (int) $variante->batches->sum('quantity');
                $price = (float) ($variante->sale_price ?: $producto?->precio_venta ?: 0);
                $lotes = $variante->batches->where('quantity', '>', 0)->count();

                return [
                    'id' => $producto?->id,
                    'variant_id' => $variante->id,
                    'nombre' => $producto?->nombre,
                    'codigo' => $producto?->codigo,
                    'presentacion' => $variante->presentation ?: $producto?->presentacion,
                    'talla' => $producto?->talla,
                    'categoria' => $producto?->categoria?->nombre,
                    'categoria_id' => $producto?->categoria_id,
                    'sku' => $variante->sku,
                    'sale_price' => $price,
                    'on_hand' => $onHand,
                    'lote_count' => $lotes,
                    'fefo' => true,
                ];
            })->values()
        );

        return $page;
    }

    public function findByIdempotency(string $key): ?Sale
    {
        return Sale::query()->where('idempotency_key', $key)->with(['items.batch', 'payments', 'user'])->first();
    }

    public function create(array $data, User $user): Sale
    {
        $existing = $this->findByIdempotency($data['idempotency_key']);
        if ($existing) {
            return $existing;
        }

        $pending = (bool) ($data['pending'] ?? false);

        if (! $pending) {
            $session = $this->cash->current($user);
            if (! $session) {
                throw new SaleException('Debes abrir caja antes de cobrar.');
            }
        } else {
            $session = $this->cash->current($user);
        }

        $sale = DB::transaction(function () use ($data, $user, $session, $pending) {
            if ($dup = Sale::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) {
                return $dup->load(['items.batch', 'payments', 'user', 'customer', 'pet']);
            }

            [$lines, $subtotal] = $this->buildLines($data['items'], ! $pending);
            $discount = round((float) ($data['discount'] ?? 0), 2);
            if ($discount < 0 || $discount > $subtotal) {
                throw new SaleException('El descuento de la venta no es válido.');
            }
            $total = round($subtotal - $discount, 2);
            $payments = [];
            $change = 0.0;
            if (! $pending) {
                [$payments, $change] = $this->normalizePayments($data['payments'] ?? [], $total);
            }

            $series = $data['series'] ?? 'B001';
            $number = ((int) Sale::query()->where('series', $series)->lockForUpdate()->max('number')) + 1;

            $customerId = $data['customer_id'] ?? null;
            $petId = $data['pet_id'] ?? null;
            if ($petId) {
                $pet = Pet::query()->findOrFail($petId);
                if ($customerId && (int) $pet->cliente_id !== (int) $customerId) {
                    throw new SaleException('La mascota no pertenece al cliente seleccionado.');
                }
                $customerId = $pet->cliente_id;
            } elseif ($customerId) {
                Cliente::query()->findOrFail($customerId);
            }

            $sale = Sale::create([
                'cash_session_id' => $session?->id,
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'pet_id' => $petId,
                'series' => $series,
                'number' => $number,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'change_amount' => $change,
                'payment_status' => $pending ? 'pendiente' : 'pagado',
                'status' => $pending ? Sale::STATUS_PENDING : Sale::STATUS_COMPLETED,
                'idempotency_key' => $data['idempotency_key'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $skipStock = ! empty($line['skip_stock']);
                unset($line['skip_stock']);
                $sale->items()->create($line);
                if (! $pending && $line['item_type'] === SaleItem::TYPE_PRODUCT && ! $skipStock && ! empty($line['batch_id'])) {
                    $this->stock->move(
                        \App\Models\Batch::query()->findOrFail($line['batch_id']),
                        StockMovement::TYPE_VENTA,
                        $line['quantity'],
                        $user,
                        'Venta '.$sale->document(),
                        'sale:'.$sale->id,
                        StockMovement::DIRECTION_OUT
                    );
                }
            }

            foreach ($payments as $payment) {
                $sale->payments()->create($payment);
            }

            return $sale->fresh(['items.batch', 'items.variante.producto', 'payments', 'user', 'customer', 'pet']);
        });
        if (! $pending) {
            $this->summaries->rebuildDate($sale->created_at);
            if (! empty($data['invoice_type'])) {
                $this->billing->emitForSale($sale, (string) $data['invoice_type'], $user);
                $sale->load('invoices');
            }
        }

        return $sale;
    }

    public function pay(Sale $sale, array $data, User $user): Sale
    {
        if ($sale->isVoided()) {
            throw new SaleException('No se puede cobrar una venta anulada.');
        }
        if (! $sale->isPending()) {
            throw new SaleException('Esta venta ya fue cobrada.');
        }

        $session = $this->cash->current($user);
        if (! $session) {
            throw new SaleException('Debes abrir caja antes de cobrar.');
        }

        $sale = DB::transaction(function () use ($sale, $data, $user, $session) {
            $sale->load('items.variante.producto');
            [$payments, $change] = $this->normalizePayments($data['payments'] ?? [], (float) $sale->total);

            foreach ($sale->items as $item) {
                if ($item->item_type !== SaleItem::TYPE_PRODUCT || ! $item->variant_id) {
                    continue;
                }
                $variante = $item->variante;
                $allocations = $this->stock->allocateFefo($variante, (int) $item->quantity, true);
                $first = $allocations[0] ?? null;
                if (! $first) {
                    throw new SaleException('Sin stock para '.$item->description);
                }
                $batch = $first['batch'];
                $item->update([
                    'batch_id' => $batch->id,
                    'unit_cost' => (float) ($batch->cost ?? $variante->cost_price ?? 0),
                ]);
                foreach ($allocations as $allocation) {
                    $this->stock->move(
                        $allocation['batch'],
                        StockMovement::TYPE_VENTA,
                        $allocation['quantity'],
                        $user,
                        'Venta '.$sale->document(),
                        'sale:'.$sale->id,
                        StockMovement::DIRECTION_OUT
                    );
                }
            }

            foreach ($payments as $payment) {
                $sale->payments()->create($payment);
            }

            $sale->update([
                'cash_session_id' => $session->id,
                'change_amount' => $change,
                'payment_status' => 'pagado',
                'status' => Sale::STATUS_COMPLETED,
            ]);

            return $sale->fresh(['items.batch', 'items.variante.producto', 'payments', 'user', 'customer', 'pet', 'invoices']);
        });

        $this->summaries->rebuildDate($sale->created_at);
        if (! empty($data['invoice_type'])) {
            $this->billing->emitForSale($sale, (string) $data['invoice_type'], $user);
            $sale->load('invoices');
        }

        return $sale;
    }

    public function void(Sale $sale, string $reason, User $user): Sale
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new SaleException('El motivo de anulación es obligatorio.');
        }
        if ($sale->isVoided()) {
            throw new SaleException('La venta ya está anulada.');
        }
        if ($sale->returns()->exists()) {
            throw new SaleException('No se puede anular una venta con devoluciones.');
        }

        $sale = DB::transaction(function () use ($sale, $reason, $user) {
            $sale->load('items.batch');
            foreach ($sale->items as $item) {
                if ($item->item_type !== SaleItem::TYPE_PRODUCT || ! $item->batch_id) {
                    continue;
                }
                $this->stock->move(
                    $item->batch,
                    StockMovement::TYPE_DEVOLUCION,
                    $item->quantity,
                    $user,
                    'Anulación '.$sale->document().': '.$reason,
                    'sale-void:'.$sale->id,
                    StockMovement::DIRECTION_IN
                );
            }

            $sale->update([
                'status' => Sale::STATUS_VOIDED,
                'void_reason' => $reason,
                'voided_by' => $user->id,
                'voided_at' => now(),
            ]);

            return $sale->fresh(['items.batch', 'payments', 'user']);
        });
        $this->summaries->rebuildDate($sale->created_at);
        $this->billing->creditNoteForVoid($sale, $reason, $user);

        return $sale;
    }

    public function returnSale(Sale $sale, array $data, User $user): SaleReturn
    {
        if ($sale->isVoided()) {
            throw new SaleException('No se puede devolver una venta anulada.');
        }

        $return = DB::transaction(function () use ($sale, $data, $user) {
            $refund = 0.0;
            $return = SaleReturn::create([
                'sale_id' => $sale->id,
                'user_id' => $user->id,
                'reason' => $data['reason'],
                'refund_total' => 0,
                'refund_method' => $data['refund_method'] ?? 'efectivo',
            ]);

            foreach ($data['items'] as $row) {
                $item = $sale->items()->where('id', $row['sale_item_id'])->first();
                if (! $item) {
                    throw new SaleException('Ítem de venta no encontrado.');
                }
                $qty = (int) $row['quantity'];
                $available = $item->quantity - $item->returnedQuantity();
                if ($qty < 1 || $qty > $available) {
                    throw new SaleException('Cantidad a devolver inválida para '.$item->description);
                }

                SaleReturnItem::create([
                    'sale_return_id' => $return->id,
                    'sale_item_id' => $item->id,
                    'quantity' => $qty,
                ]);

                $unitNet = $item->quantity > 0
                    ? ((float) $item->line_total) / $item->quantity
                    : 0;
                $refund += round($unitNet * $qty, 2);

                if ($item->item_type === SaleItem::TYPE_PRODUCT && $item->batch_id) {
                    $this->stock->move(
                        $item->batch,
                        StockMovement::TYPE_DEVOLUCION,
                        $qty,
                        $user,
                        'Devolución '.$sale->document(),
                        'sale-return:'.$return->id,
                        StockMovement::DIRECTION_IN
                    );
                }
            }

            $return->update(['refund_total' => round($refund, 2)]);

            return $return->fresh(['items.saleItem', 'sale']);
        });
        $this->summaries->rebuildDate($sale->created_at);
        $this->billing->creditNoteForReturn($sale, $return, $user);

        return $return;
    }

    public function receipt(Sale $sale): array
    {
        $sale->load(['items.variante.producto', 'payments', 'user', 'returns.items', 'customer.persona', 'pet', 'invoices']);

        return [
            'document' => $sale->document(),
            'fecha' => $sale->created_at?->toDateTimeString(),
            'cajero' => $sale->user?->name,
            'cliente' => $sale->customer?->displayName(),
            'mascota' => $sale->pet?->nombre,
            'status' => $sale->status,
            'items' => $sale->items->map(fn (SaleItem $item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'line_total' => $item->line_total,
                'lote' => $item->batch?->batch_code,
            ]),
            'subtotal' => $sale->subtotal,
            'discount' => $sale->discount,
            'total' => $sale->total,
            'payments' => $sale->payments->map(fn (SalePayment $p) => [
                'method' => $p->method,
                'amount' => $p->amount,
            ]),
            'change_amount' => $sale->change_amount,
            'void_reason' => $sale->void_reason,
            'invoice' => $this->invoiceSummary($sale),
        ];
    }

    public function searchCatalog(string $q): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $variants = Variante::query()
            ->with(['producto', 'batches'])
            ->where('activo', true)
            ->where(function ($query) use ($q) {
                $query->where('sku', 'like', '%'.$q.'%')
                    ->orWhere('barcode', $q)
                    ->orWhere('presentation', 'like', '%'.$q.'%')
                    ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', '%'.$q.'%')->orWhere('codigo', $q));
            })
            ->limit(20)
            ->get();

        return $variants->map(function (Variante $variante) {
            $onHand = (int) $variante->batches->sum('quantity');

            return [
                'variant_id' => $variante->id,
                'sku' => $variante->sku,
                'barcode' => $variante->barcode,
                'nombre' => $variante->producto?->nombre,
                'presentation' => $variante->presentation,
                'sale_price' => $variante->sale_price,
                'on_hand' => $onHand,
            ];
        })->all();
    }

    private function buildLines(array $items, bool $allocate = true): array
    {
        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $row) {
            $type = $row['item_type'] ?? SaleItem::TYPE_PRODUCT;
            $qty = (int) $row['quantity'];
            $lineDiscount = round((float) ($row['discount'] ?? 0), 2);

            if ($type === SaleItem::TYPE_SERVICE) {
                $price = round((float) $row['unit_price'], 2);
                $lineTotal = round(($price * $qty) - $lineDiscount, 2);
                if ($lineTotal < 0) {
                    throw new SaleException('El descuento del servicio no es válido.');
                }
                $lines[] = [
                    'item_type' => SaleItem::TYPE_SERVICE,
                    'variant_id' => null,
                    'batch_id' => null,
                    'description' => $row['description'],
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'unit_cost' => 0,
                    'discount' => $lineDiscount,
                    'line_total' => $lineTotal,
                ];
                $subtotal += $lineTotal;
                continue;
            }

            $variante = Variante::query()->with('producto')->findOrFail($row['variant_id']);
            $price = isset($row['unit_price']) ? round((float) $row['unit_price'], 2) : (float) $variante->sale_price;
            if ($price <= 0) {
                throw new SaleException('La variante '.$variante->sku.' no tiene precio de venta.');
            }

            $lineTotal = round(($price * $qty) - $lineDiscount, 2);
            if ($lineTotal < 0) {
                throw new SaleException('El descuento del producto no es válido.');
            }

            if (! $allocate) {
                $lines[] = [
                    'item_type' => SaleItem::TYPE_PRODUCT,
                    'variant_id' => $variante->id,
                    'batch_id' => null,
                    'description' => trim(($variante->producto?->nombre ?? '').' '.($variante->presentation ?: $variante->producto?->talla)),
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'unit_cost' => (float) ($variante->cost_price ?? $variante->producto?->precio_compra ?? 0),
                    'discount' => $lineDiscount,
                    'line_total' => $lineTotal,
                ];
                $subtotal += $lineTotal;
                continue;
            }

            if (! empty($row['skip_stock'])) {
                $batch = \App\Models\Batch::query()->findOrFail($row['batch_id']);
                $lineTotal = round(($price * $qty) - $lineDiscount, 2);
                $lines[] = [
                    'item_type' => SaleItem::TYPE_PRODUCT,
                    'variant_id' => $variante->id,
                    'batch_id' => $batch->id,
                    'description' => trim(($variante->producto?->nombre ?? '').' '.$variante->presentation),
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'unit_cost' => (float) ($batch->cost ?? $variante->cost_price ?? 0),
                    'discount' => $lineDiscount,
                    'line_total' => $lineTotal,
                    'skip_stock' => true,
                ];
                $subtotal += $lineTotal;
                continue;
            }

            $allocations = $this->stock->allocateFefo($variante, $qty, true);
            $remainingDiscount = $lineDiscount;
            foreach ($allocations as $index => $allocation) {
                $take = $allocation['quantity'];
                $shareDiscount = $index === count($allocations) - 1
                    ? $remainingDiscount
                    : round($lineDiscount * ($take / $qty), 2);
                $remainingDiscount = round($remainingDiscount - $shareDiscount, 2);
                $lineTotal = round(($price * $take) - $shareDiscount, 2);
                $cost = (float) ($allocation['batch']->cost ?? $variante->cost_price ?? 0);
                $lines[] = [
                    'item_type' => SaleItem::TYPE_PRODUCT,
                    'variant_id' => $variante->id,
                    'batch_id' => $allocation['batch']->id,
                    'description' => trim(($variante->producto?->nombre ?? '').' '.$variante->presentation),
                    'quantity' => $take,
                    'unit_price' => $price,
                    'unit_cost' => $cost,
                    'discount' => $shareDiscount,
                    'line_total' => $lineTotal,
                ];
                $subtotal += $lineTotal;
            }
        }

        return [$lines, round($subtotal, 2)];
    }

    /**
     * @return array{0: list<array{method: string, amount: float}>, 1: float}
     */
    private function normalizePayments(array $payments, float $total): array
    {
        if ($payments === []) {
            throw new SaleException('Debes registrar al menos un pago.');
        }

        $normalized = [];
        $cash = 0.0;
        $other = 0.0;
        foreach ($payments as $payment) {
            $amount = round((float) $payment['amount'], 2);
            if ($amount <= 0) {
                throw new SaleException('El monto de pago debe ser mayor a cero.');
            }
            $method = $payment['method'];
            $normalized[] = ['method' => $method, 'amount' => $amount];
            if ($method === 'efectivo') {
                $cash += $amount;
            } else {
                $other += $amount;
            }
        }

        if ($other - $total > 0.009) {
            throw new SaleException('Los pagos que no son efectivo superan el total.');
        }
        $neededCash = round(max($total - $other, 0), 2);
        if ($cash + 0.009 < $neededCash) {
            throw new SaleException('El pago es insuficiente. Falta S/ '.number_format($neededCash - $cash, 2));
        }

        return [$normalized, round($cash - $neededCash, 2)];
    }

    private function invoiceSummary(Sale $sale): ?array
    {
        $inv = $sale->invoices
            ->whereIn('document_type', [Invoice::TYPE_BOLETA, Invoice::TYPE_FACTURA])
            ->sortByDesc('id')
            ->first();
        if (! $inv) {
            return null;
        }

        return [
            'id' => $inv->id,
            'document' => $inv->document(),
            'document_type' => $inv->document_type,
            'status' => $inv->status,
            'gravada' => $inv->gravada,
            'igv' => $inv->igv,
            'total' => $inv->total,
        ];
    }

    private function seesAll(User $user): bool
    {
        return $user->can('reports.view_all') || $user->hasRole('Administrador') || $user->can('sales.charge');
    }

    private function sharedPending(Request $request): bool
    {
        return $request->query('status') === Sale::STATUS_PENDING
            || $request->query('payment_status') === 'pendiente';
    }
}
