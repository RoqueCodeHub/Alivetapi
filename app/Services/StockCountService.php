<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\Batch;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Counts;
use Illuminate\Support\Facades\DB;

class StockCountService
{
    public function __construct(private readonly StockService $stock)
    {
    }

    public function summary(): array
    {
        $today = now()->toDateString();
        $row = StockCount::query()->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as borradores,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as aprobados,
            SUM(CASE WHEN DATE(counted_at) = ? THEN 1 ELSE 0 END) as de_hoy
        ', [StockCount::STATUS_DRAFT, StockCount::STATUS_APPROVED, $today])->first();

        return Counts::ints($row, ['total', 'borradores', 'aprobados', 'de_hoy']);
    }

    public function create(User $user, ?string $notes = null): StockCount
    {
        return DB::transaction(function () use ($user, $notes) {
            $count = StockCount::create([
                'status' => StockCount::STATUS_DRAFT,
                'notes' => $notes,
                'user_id' => $user->id,
            ]);

            $batches = Batch::query()->orderBy('id')->get();
            foreach ($batches as $batch) {
                StockCountItem::create([
                    'stock_count_id' => $count->id,
                    'variant_id' => $batch->variant_id,
                    'batch_id' => $batch->id,
                    'system_qty' => $batch->quantity,
                    'counted_qty' => null,
                ]);
            }

            return $count->load(['items.batch', 'items.variante.producto', 'user']);
        });
    }

    public function record(StockCount $count, array $items): StockCount
    {
        if ($count->isApproved()) {
            throw new StockException('El conteo ya fue aprobado.');
        }

        return DB::transaction(function () use ($count, $items) {
            foreach ($items as $row) {
                $item = $count->items()->where('id', $row['id'])->first();
                if (! $item) {
                    throw new StockException('Línea de conteo no encontrada.');
                }
                $item->update(['counted_qty' => (int) $row['counted_qty']]);
            }
            $count->update(['counted_at' => now()]);

            return $count->fresh(['items.batch', 'items.variante.producto', 'user']);
        });
    }

    public function approve(StockCount $count, User $user): StockCount
    {
        if ($count->isApproved()) {
            throw new StockException('El conteo ya fue aprobado.');
        }

        $pending = $count->items()->whereNull('counted_qty')->exists();
        if ($pending) {
            throw new StockException('Hay lotes sin cantidad contada.');
        }

        return DB::transaction(function () use ($count, $user) {
            foreach ($count->items()->with('batch')->get() as $item) {
                $diff = $item->difference();
                if ($diff === 0) {
                    continue;
                }
                $direction = $diff > 0 ? StockMovement::DIRECTION_IN : StockMovement::DIRECTION_OUT;
                $this->stock->adjust(
                    $item->batch,
                    $direction,
                    abs((int) $diff),
                    'Inventario físico #'.$count->id,
                    $user,
                    'count:'.$count->id
                );
            }

            $count->update([
                'status' => StockCount::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            return $count->fresh(['items.batch', 'items.variante.producto', 'user', 'approver']);
        });
    }
}
