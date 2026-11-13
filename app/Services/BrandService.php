<?php

namespace App\Services;

use App\Models\Marca;
use App\Support\Counts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BrandService
{
    public function all(): Collection
    {
        return Marca::query()->orderBy('nombre')->get();
    }

    public function paginate(int $perPage, ?string $search = null): LengthAwarePaginator
    {
        return Marca::query()
            ->when($search, fn ($query) => $query->where('nombre', 'like', '%'.$search.'%'))
            ->orderBy('nombre')
            ->paginate($perPage);
    }

    public function summary(): array
    {
        $row = Marca::query()->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activas,
            SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivas,
            SUM(CASE WHEN EXISTS (SELECT 1 FROM productos WHERE productos.marca_id = marcas.id) THEN 1 ELSE 0 END) as con_productos
        ')->first();

        return Counts::ints($row, ['total', 'activas', 'inactivas', 'con_productos']);
    }

    public function create(array $data): Marca
    {
        return Marca::create($data);
    }

    public function update(Marca $marca, array $data): Marca
    {
        $marca->update($data);

        return $marca->fresh();
    }
}
