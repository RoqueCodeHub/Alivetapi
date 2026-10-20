<?php

namespace App\Services;

use App\Exceptions\PurchaseException;
use App\Models\Batch;
use App\Models\CostHistory;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Variante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(private readonly StockService $stock)
    {
    }

    public function paginate(?string $status = null): LengthAwarePaginator
    {
        return Purchase::query()
            ->with(['supplier', 'items.variante.producto'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function create(array $data, User $user): Purchase
    {
        return DB::transaction(function () use ($data, $user) {
            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'comprobante' => $data['comprobante'] ?? null,
                'fecha' => $data['fecha'],
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => Purchase::STATUS_DRAFT,
                'payment_status' => Purchase::PAYMENT_PENDIENTE,
                'user_id' => $user->id,
                'total' => 0,
                'paid_amount' => 0,
            ]);

            $this->syncItems($purchase, $data['items']);
            $this->recalculateTotal($purchase);

            return $purchase->fresh(['supplier', 'items.variante.producto', 'items.batch']);
        });
    }

    public function update(Purchase $purchase, array $data): Purchase
    {
        $this->assertDraft($purchase);

        return DB::transaction(function () use ($purchase, $data) {
            $purchase->fill([
                'supplier_id' => $data['supplier_id'] ?? $purchase->supplier_id,
                'comprobante' => array_key_exists('comprobante', $data) ? $data['comprobante'] : $purchase->comprobante,
                'fecha' => $data['fecha'] ?? $purchase->fecha,
                'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $purchase->due_date,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $purchase->notes,
            ])->save();

            if (isset($data['items'])) {
                $purchase->items()->delete();
                $this->syncItems($purchase, $data['items']);
            }

            $this->recalculateTotal($purchase);

            return $purchase->fresh(['supplier', 'items.variante.producto', 'items.batch']);
        });
    }

    public function confirm(Purchase $purchase, User $user): Purchase
    {
        $this->assertDraft($purchase);

        if ($purchase->items()->count() === 0) {
            throw new PurchaseException('La compra no tiene ítems.');
        }

        return DB::transaction(function () use ($purchase, $user) {
            foreach ($purchase->items()->get() as $item) {
                $batch = $this->resolveBatch($item);
                $this->stock->move(
                    $batch,
                    StockMovement::TYPE_COMPRA,
                    $item->quantity,
                    $user,
                    'Compra '.$purchase->comprobante,
                    'purchase:'.$purchase->id
                );

                $item->update(['batch_id' => $batch->id]);

                $variante = Variante::query()->findOrFail($item->variant_id);
                $variante->update(['cost_price' => $item->cost]);

                CostHistory::create([
                    'variant_id' => $variante->id,
                    'purchase_id' => $purchase->id,
                    'purchase_item_id' => $item->id,
                    'cost' => $item->cost,
                    'user_id' => $user->id,
                ]);

                $batch->update(['cost' => $item->cost]);
            }

            $this->recalculateTotal($purchase->fresh());
            $purchase->update([
                'status' => Purchase::STATUS_CONFIRMED,
                'confirmed_by' => $user->id,
                'confirmed_at' => now(),
                'payment_status' => ((float) $purchase->fresh()->paid_amount) >= ((float) $purchase->fresh()->total) && (float) $purchase->total > 0
                    ? Purchase::PAYMENT_PAGADO
                    : Purchase::PAYMENT_PENDIENTE,
            ]);

            return $purchase->fresh(['supplier', 'items.variante.producto', 'items.batch', 'payments']);
        });
    }

    public function pay(Purchase $purchase, array $data, User $user): Purchase
    {
        if (! $purchase->isConfirmed()) {
            throw new PurchaseException('Solo se registran pagos en compras confirmadas.');
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new PurchaseException('El monto del pago debe ser mayor a cero.');
        }

        $balance = $purchase->balance();
        if ($amount - $balance > 0.009) {
            throw new PurchaseException('El pago supera el saldo pendiente ('.$balance.').');
        }

        return DB::transaction(function () use ($purchase, $data, $user, $amount) {
            PurchasePayment::create([
                'purchase_id' => $purchase->id,
                'amount' => $amount,
                'method' => $data['method'] ?? 'transferencia',
                'reference' => $data['reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'user_id' => $user->id,
            ]);

            $paid = round((float) $purchase->paid_amount + $amount, 2);
            $total = (float) $purchase->total;
            $status = $paid >= $total
                ? Purchase::PAYMENT_PAGADO
                : ($paid > 0 ? Purchase::PAYMENT_PARCIAL : Purchase::PAYMENT_PENDIENTE);

            $purchase->update([
                'paid_amount' => $paid,
                'payment_status' => $status,
            ]);

            return $purchase->fresh(['supplier', 'items.variante.producto', 'payments']);
        });
    }

    public function payables(): LengthAwarePaginator
    {
        return Purchase::query()
            ->with('supplier')
            ->where('status', Purchase::STATUS_CONFIRMED)
            ->where('payment_status', '!=', Purchase::PAYMENT_PAGADO)
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate(15);
    }

    public function costHistory(Variante $variante)
    {
        return CostHistory::query()
            ->with('purchase')
            ->where('variant_id', $variante->id)
            ->orderByDesc('id')
            ->get();
    }

    private function syncItems(Purchase $purchase, array $items): void
    {
        foreach ($items as $row) {
            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'variant_id' => $row['variant_id'],
                'batch_code' => $row['batch_code'],
                'expiry_date' => $row['expiry_date'] ?? null,
                'quantity' => $row['quantity'],
                'cost' => $row['cost'],
            ]);
        }
    }

    private function recalculateTotal(Purchase $purchase): void
    {
        $total = round($purchase->items()->get()->sum(fn (PurchaseItem $item) => $item->lineTotal()), 2);
        $purchase->update(['total' => $total]);
    }

    private function resolveBatch(PurchaseItem $item): Batch
    {
        $batch = Batch::query()
            ->where('variant_id', $item->variant_id)
            ->where('batch_code', $item->batch_code)
            ->first();

        if (! $batch) {
            $batch = Batch::create([
                'variant_id' => $item->variant_id,
                'batch_code' => $item->batch_code,
                'expiry_date' => $item->expiry_date,
                'quantity' => 0,
                'cost' => $item->cost,
            ]);
        } elseif ($item->expiry_date && ! $batch->expiry_date) {
            $batch->update(['expiry_date' => $item->expiry_date]);
        }

        return $batch;
    }

    private function assertDraft(Purchase $purchase): void
    {
        if ($purchase->isConfirmed()) {
            throw new PurchaseException('La compra ya fue confirmada.');
        }
    }
}
