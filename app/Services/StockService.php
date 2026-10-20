<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\Batch;
use App\Models\Producto;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockService
{
    public function move(
        Batch $batch,
        string $type,
        int $quantity,
        User $user,
        string $reason,
        ?string $reference = null,
        ?string $direction = null,
    ): StockMovement {
        if ($quantity < 1) {
            throw new StockException('La cantidad debe ser mayor a cero.');
        }

        $direction = $direction ?? $this->defaultDirection($type);
        if (! in_array($direction, [StockMovement::DIRECTION_IN, StockMovement::DIRECTION_OUT], true)) {
            throw new StockException('La dirección del movimiento no es válida.');
        }

        return DB::transaction(function () use ($batch, $type, $quantity, $user, $reason, $reference, $direction) {
            $locked = Batch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $signed = $direction === StockMovement::DIRECTION_OUT ? -$quantity : $quantity;
            $newQty = $locked->quantity + $signed;
            if ($newQty < 0) {
                throw new StockException('Stock insuficiente en el lote '.$locked->batch_code);
            }
            $locked->quantity = $newQty;
            $locked->save();

            $movement = StockMovement::create([
                'variant_id' => $locked->variant_id,
                'batch_id' => $locked->id,
                'type' => $type,
                'direction' => $direction,
                'quantity' => $quantity,
                'reason' => $reason,
                'user_id' => $user->id,
                'reference' => $reference,
            ]);

            $this->refreshProductoStock((int) $locked->variant_id);

            return $movement;
        });
    }

    public function rebuildProductoStock(?int $productoId = null): int
    {
        $updated = 0;
        Producto::query()
            ->when($productoId, fn ($builder) => $builder->whereKey($productoId))
            ->orderBy('id')
            ->chunkById(100, function ($productos) use (&$updated) {
                foreach ($productos as $producto) {
                    $sum = (int) DB::table('batches')
                        ->join('variantes', 'variantes.id', '=', 'batches.variant_id')
                        ->where('variantes.producto_id', $producto->id)
                        ->sum('batches.quantity');
                    $producto->update(['stock_actual' => $sum]);
                    $updated++;
                }
            });

        return $updated;
    }

    private function refreshProductoStock(int $variantId): void
    {
        $productoId = Variante::query()->whereKey($variantId)->value('producto_id');
        if (! $productoId) {
            return;
        }

        $sum = (int) DB::table('batches')
            ->join('variantes', 'variantes.id', '=', 'batches.variant_id')
            ->where('variantes.producto_id', $productoId)
            ->sum('batches.quantity');

        Producto::query()->whereKey($productoId)->update(['stock_actual' => $sum]);
    }

    public function adjust(Batch $batch, string $direction, int $quantity, string $reason, User $user, ?string $reference = null): StockMovement
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new StockException('El motivo del ajuste es obligatorio.');
        }

        $dir = $direction === 'salida' || $direction === StockMovement::DIRECTION_OUT
            ? StockMovement::DIRECTION_OUT
            : StockMovement::DIRECTION_IN;

        return $this->move($batch, StockMovement::TYPE_AJUSTE, $quantity, $user, $reason, $reference, $dir);
    }

    /**
     * @return list<array{batch: Batch, quantity: int}>
     */
    public function allocateFefo(Variante $variante, int $quantity, bool $lock = false): array
    {
        if ($quantity < 1) {
            throw new StockException('La cantidad FEFO debe ser mayor a cero.');
        }

        $query = $variante->batches()
            ->where('quantity', '>', 0)
            ->orderByRaw('expiry_date is null')
            ->orderBy('expiry_date')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $batches = $query->get();
        $remaining = $quantity;
        $allocations = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($batch->quantity, $remaining);
            if ($take < 1) {
                continue;
            }
            $allocations[] = ['batch' => $batch, 'quantity' => $take];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new StockException('Stock insuficiente para cubrir '.$quantity.' unidades (FEFO).');
        }

        return $allocations;
    }

    public function fefoBatches(Variante $variante): Collection
    {
        return $variante->batches()
            ->where('quantity', '>', 0)
            ->orderByRaw('expiry_date is null')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get();
    }

    public function openBox(Batch $batch, int $boxes, User $user): array
    {
        $variante = $batch->variante;
        $perBox = (int) $variante->units_per_box;
        if ($perBox < 2) {
            throw new StockException('La variante no tiene conversión de caja (units_per_box).');
        }
        if ($boxes < 1) {
            throw new StockException('Indica cuántas cajas abrir.');
        }

        return DB::transaction(function () use ($batch, $boxes, $user, $variante, $perBox) {
            $reference = 'open-box:'.Str::lower(Str::random(6));
            $out = $this->move(
                $batch->fresh(),
                StockMovement::TYPE_AJUSTE,
                $boxes,
                $user,
                'Apertura de caja a unidades de venta',
                $reference,
                StockMovement::DIRECTION_OUT
            );

            $unpacked = Batch::query()->firstOrCreate(
                [
                    'variant_id' => $variante->id,
                    'batch_code' => $batch->batch_code.'-U',
                ],
                [
                    'expiry_date' => $batch->expiry_date,
                    'quantity' => 0,
                    'cost' => $batch->cost,
                ]
            );

            $in = $this->move(
                $unpacked->fresh(),
                StockMovement::TYPE_AJUSTE,
                $boxes * $perBox,
                $user,
                'Conversión '.$boxes.' caja(s) × '.$perBox.' unidades',
                $reference,
                StockMovement::DIRECTION_IN
            );

            return [
                'opened' => $boxes,
                'units' => $boxes * $perBox,
                'source_batch' => $batch->fresh(),
                'unit_batch' => $unpacked->fresh(),
                'movements' => [$out, $in],
            ];
        });
    }

    public function kardex(Variante $variante): array
    {
        $movements = StockMovement::query()
            ->with(['user', 'batch'])
            ->where('variant_id', $variante->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $saldo = 0;
        $lines = [];
        foreach ($movements as $movement) {
            $saldo += $movement->signedQuantity();
            $lines[] = [
                'id' => $movement->id,
                'fecha' => $movement->created_at?->toDateTimeString(),
                'usuario' => $movement->user?->name,
                'movimiento' => $movement->type,
                'direction' => $movement->direction,
                'cantidad' => $movement->quantity,
                'saldo' => $saldo,
                'motivo' => $movement->reason,
                'referencia' => $movement->reference,
                'lote' => $movement->batch?->batch_code,
            ];
        }

        return $lines;
    }

    public function receive(array $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user) {
            $producto = Producto::query()->findOrFail($data['producto_id']);
            $variante = $this->resolveReceiptVariant($producto, $data);

            if (isset($data['sale_price']) && $data['sale_price'] !== null && $data['sale_price'] !== '') {
                $variante->sale_price = $data['sale_price'];
                $producto->precio_venta = $data['sale_price'];
            }
            if (isset($data['cost']) && $data['cost'] !== null && $data['cost'] !== '') {
                $variante->cost_price = $data['cost'];
                $producto->precio_compra = $data['cost'];
            }
            $variante->save();
            $producto->save();

            $code = trim((string) ($data['batch_code'] ?? ''));
            if ($code === '') {
                $code = 'LOT-'.now()->format('YmdHis');
            }

            $batch = Batch::query()->firstOrCreate(
                ['variant_id' => $variante->id, 'batch_code' => $code],
                [
                    'quantity' => 0,
                    'cost' => $data['cost'] ?? null,
                    'expiry_date' => $data['expiry_date'] ?? null,
                ]
            );
            if (array_key_exists('cost', $data) && $data['cost'] !== null && $data['cost'] !== '') {
                $batch->cost = $data['cost'];
            }
            if (! empty($data['expiry_date'])) {
                $batch->expiry_date = $data['expiry_date'];
            }
            $batch->save();

            $this->move(
                $batch->fresh(),
                StockMovement::TYPE_COMPRA,
                (int) $data['quantity'],
                $user,
                $data['reason'] ?? 'Ingreso de stock',
                'receipt'
            );

            $variante->load(['producto.categoria', 'batches']);

            return [
                'batch' => $batch->fresh(),
                'variant' => $variante,
                'on_hand' => (int) $variante->batches->sum('quantity'),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveReceiptVariant(Producto $producto, array $data): Variante
    {
        if (! empty($data['variant_id'])) {
            return Variante::query()
                ->where('producto_id', $producto->id)
                ->whereKey($data['variant_id'])
                ->firstOrFail();
        }

        $presentation = trim((string) ($data['presentation'] ?? $producto->presentacion ?? ''));
        $query = Variante::query()->where('producto_id', $producto->id);
        if ($presentation === '') {
            $existing = $query->orderBy('id')->first();
            if ($existing) {
                return $existing;
            }
        } else {
            $existing = $query->get()->first(function (Variante $variante) use ($presentation) {
                return mb_strtolower((string) $variante->presentation) === mb_strtolower($presentation);
            });
            if ($existing) {
                return $existing;
            }
        }

        return Variante::query()->create([
            'producto_id' => $producto->id,
            'sku' => $producto->codigo ?: ('VAR-'.$producto->id.'-'.now()->format('His')),
            'presentation' => $presentation !== '' ? $presentation : ($producto->presentacion ?: $producto->talla),
            'unit' => $producto->unidad_medida ?: 'unidad',
            'sale_unit' => $producto->unidad_medida ?: 'unidad',
            'cost_price' => $data['cost'] ?? $producto->precio_compra,
            'sale_price' => $data['sale_price'] ?? $producto->precio_venta,
            'activo' => true,
        ]);
    }

    public function catalog(?string $search = null)
    {
        return Variante::query()
            ->with(['producto.categoria', 'batches'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('sku', 'like', '%'.$search.'%')
                        ->orWhere('presentation', 'like', '%'.$search.'%')
                        ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('id')
            ->paginate(20);
    }

    public function variantStock(Variante $variante): array
    {
        $batches = $variante->batches()->orderByRaw('expiry_date is null')->orderBy('expiry_date')->get();
        $onHand = (int) $batches->sum('quantity');

        return [
            'variant' => $variante,
            'on_hand' => $onHand,
            'min_stock' => (int) $variante->min_stock,
            'low_stock' => $variante->min_stock > 0 && $onHand <= $variante->min_stock,
            'batches' => $batches,
            'fefo' => $this->fefoBatches($variante),
        ];
    }

    public function lowStock()
    {
        return Variante::query()
            ->with(['producto', 'batches'])
            ->where('min_stock', '>', 0)
            ->get()
            ->filter(function (Variante $variante) {
                $onHand = (int) $variante->batches->sum('quantity');

                return $onHand <= $variante->min_stock;
            })
            ->values();
    }

    public function expiryAlerts(): array
    {
        $today = now()->startOfDay();
        $batches = Batch::query()
            ->with('variante.producto')
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date')
            ->get();

        $expired = [];
        $d30 = [];
        $d60 = [];
        $d90 = [];

        foreach ($batches as $batch) {
            $expiry = $batch->expiry_date->startOfDay();
            $payload = $this->alertPayload($batch);
            if ($expiry->lt($today)) {
                $expired[] = $payload;
                continue;
            }
            $days = (int) $today->diffInDays($expiry);
            if ($days <= 30) {
                $d30[] = $payload;
            } elseif ($days <= 60) {
                $d60[] = $payload;
            } elseif ($days <= 90) {
                $d90[] = $payload;
            }
        }

        return [
            'vencido' => $expired,
            'vence_30' => $d30,
            'vence_60' => $d60,
            'vence_90' => $d90,
        ];
    }

    public function inventoryExportRows(): array
    {
        $rows = [];
        $variants = Variante::query()->with(['producto', 'batches'])->orderBy('id')->get();
        foreach ($variants as $variant) {
            foreach ($variant->batches as $batch) {
                $rows[] = [
                    $variant->sku,
                    $variant->producto?->nombre,
                    $variant->presentation,
                    $batch->batch_code,
                    optional($batch->expiry_date)?->toDateString(),
                    $batch->quantity,
                    $batch->cost,
                    $variant->min_stock,
                    $variant->unit,
                    $variant->sale_unit,
                    $variant->units_per_box,
                ];
            }
        }

        return $rows;
    }

    private function defaultDirection(string $type): string
    {
        if (in_array($type, StockMovement::OUTBOUND_TYPES, true)) {
            return StockMovement::DIRECTION_OUT;
        }
        if (in_array($type, StockMovement::INBOUND_TYPES, true) || $type === StockMovement::TYPE_AJUSTE) {
            return StockMovement::DIRECTION_IN;
        }

        throw new StockException('Tipo de movimiento no válido.');
    }

    private function alertPayload(Batch $batch): array
    {
        return [
            'batch_id' => $batch->id,
            'batch_code' => $batch->batch_code,
            'expiry_date' => optional($batch->expiry_date)?->toDateString(),
            'quantity' => $batch->quantity,
            'variant_id' => $batch->variant_id,
            'sku' => $batch->variante?->sku,
            'producto' => $batch->variante?->producto?->nombre,
            'presentation' => $batch->variante?->presentation,
        ];
    }
}
