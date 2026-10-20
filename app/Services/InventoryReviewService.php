<?php

namespace App\Services;

use App\Models\Variante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InventoryReviewService
{
    public function pending(): LengthAwarePaginator
    {
        return Variante::query()
            ->with(['producto.categoria', 'batches'])
            ->where('needs_review', true)
            ->orderByDesc('updated_at')
            ->paginate(20);
    }

    public function resolve(Variante $variante, array $data): Variante
    {
        $variante->update($data);

        return $variante->fresh(['producto.categoria', 'batches']);
    }
}
