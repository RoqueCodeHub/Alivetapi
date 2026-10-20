<?php

namespace App\Services;

use App\Exceptions\StockException;
use App\Models\Categoria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CategoryService
{
    public function tree(): Collection
    {
        return Categoria::query()
            ->with(['children' => fn ($q) => $q->with('children')])
            ->whereNull('parent_id')
            ->orderBy('nombre')
            ->get();
    }

    public function paginate(Request $request): LengthAwarePaginator
    {
        $parentId = $request->query('parent_id');
        $perPage = (int) $request->query('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        return Categoria::query()
            ->with('parent')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = trim((string) $request->query('q'));
                $query->where(function ($inner) use ($q) {
                    $inner->where('nombre', 'like', '%'.$q.'%')
                        ->orWhere('descripcion', 'like', '%'.$q.'%');
                });
            })
            ->when($parentId === 'root', fn ($query) => $query->whereNull('parent_id'))
            ->when(is_numeric($parentId) && (int) $parentId > 0, fn ($query) => $query->where('parent_id', (int) $parentId))
            ->when($request->filled('activo'), fn ($query) => $query->where('activo', $request->boolean('activo')))
            ->orderBy('nombre')
            ->paginate($perPage);
    }

    public function create(array $data): Categoria
    {
        return Categoria::create($data);
    }

    public function update(Categoria $categoria, array $data): Categoria
    {
        $categoria->update($data);

        return $categoria->fresh('children');
    }

    public function delete(Categoria $categoria): void
    {
        if ($categoria->children()->exists()) {
            throw new StockException('No se puede eliminar la categoría porque tiene subcategorías.');
        }

        if ($categoria->productos()->exists()) {
            throw new StockException('No se puede eliminar la categoría porque tiene productos asociados.');
        }

        $categoria->delete();
    }
}
