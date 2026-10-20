<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Variante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function search(Request $request): LengthAwarePaginator
    {
        $query = $this->filteredQuery($request);
        $perPage = (int) $request->query('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        if ($request->boolean('grouped')) {
            return $this->paginateGrouped($query, $request, $perPage);
        }

        return $query
            ->with(['categoria', 'marca', 'proveedor', 'variantes.batches'])
            ->orderBy('nombre')
            ->paginate($perPage);
    }

    public function create(array $data): Producto
    {
        return DB::transaction(function () use ($data) {
            $data['codigo'] = $data['codigo'] ?? $this->nextCode();
            $data['precio_venta'] = $data['precio_venta'] ?? 0;
            $producto = Producto::create($data)->load(['categoria', 'marca', 'proveedor', 'variantes']);
            $this->syncPrimaryVariant($producto, $data);

            return $producto->fresh(['categoria', 'marca', 'proveedor', 'variantes']);
        });
    }

    public function update(Producto $producto, array $data): Producto
    {
        return DB::transaction(function () use ($producto, $data) {
            $applyGroup = (bool) ($data['apply_to_group'] ?? false);
            unset($data['apply_to_group']);

            $producto->update($data);
            $producto = $producto->fresh();
            $this->syncPrimaryVariant($producto, $data);

            if ($applyGroup) {
                $this->applyCommercialDataToGroup($producto, $data);
            }

            return $producto->fresh(['categoria', 'marca', 'proveedor', 'variantes']);
        });
    }

    public function deactivate(Producto $producto): Producto
    {
        $producto->update(['activo' => false]);

        return $producto->fresh(['categoria', 'marca', 'variantes']);
    }

    public function addVariant(Producto $producto, array $data): Variante
    {
        return $producto->variantes()->create($data);
    }

    public function updateVariant(Variante $variante, array $data): Variante
    {
        $variante->update($data);

        return $variante->fresh('producto');
    }

    public function nextCode(): string
    {
        $last = Producto::query()
            ->where('codigo', 'like', 'VET-%')
            ->orderByDesc('id')
            ->value('codigo');
        $n = 1;
        if ($last && preg_match('/VET-(\d+)/', $last, $m)) {
            $n = ((int) $m[1]) + 1;
        }

        do {
            $code = sprintf('VET-%04d', $n);
            $n++;
        } while (Producto::query()->where('codigo', $code)->exists());

        return $code;
    }

    private function filteredQuery(Request $request): Builder
    {
        $q = trim((string) $request->query('q', $request->query('nombre', '')));

        return Producto::query()
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('nombre', 'like', '%'.$q.'%')
                        ->orWhere('codigo', 'like', '%'.$q.'%')
                        ->orWhere('presentacion', 'like', '%'.$q.'%')
                        ->orWhere('talla', 'like', '%'.$q.'%')
                        ->orWhere('especie', 'like', '%'.$q.'%')
                        ->orWhereHas('categoria', fn ($c) => $c->where('nombre', 'like', '%'.$q.'%'))
                        ->orWhereHas('marca', fn ($m) => $m->where('nombre', 'like', '%'.$q.'%'))
                        ->orWhereHas('proveedor', fn ($p) => $p->where('nombre', 'like', '%'.$q.'%')->orWhere('empresa', 'like', '%'.$q.'%'));
                });
            })
            ->when($request->filled('sku'), function ($builder) use ($request) {
                $builder->where(function ($inner) use ($request) {
                    $inner->where('codigo', 'like', '%'.$request->sku.'%')
                        ->orWhereHas('variantes', fn ($v) => $v->where('sku', 'like', '%'.$request->sku.'%'));
                });
            })
            ->when($request->filled('barcode'), function ($builder) use ($request) {
                $builder->where(function ($inner) use ($request) {
                    $inner->where('codigo', $request->barcode)
                        ->orWhereHas('variantes', fn ($v) => $v->where('barcode', $request->barcode));
                });
            })
            ->when($request->filled('category_id'), function ($builder) use ($request) {
                $ids = Categoria::idsWithDescendants((int) $request->category_id);
                $builder->whereIn('categoria_id', $ids);
            })
            ->when($request->filled('especie'), fn ($builder) => $builder->where('especie', $request->especie))
            ->when($request->filled('tipo'), fn ($builder) => $builder->where('tipo', $request->tipo))
            ->when($request->filled('proveedor_id'), fn ($builder) => $builder->where('proveedor_id', $request->proveedor_id))
            ->when($request->filled('marca_id'), fn ($builder) => $builder->where('marca_id', $request->marca_id))
            ->when($request->has('activo') && $request->activo !== '', fn ($builder) => $builder->where('activo', filter_var($request->activo, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->boolean('low_stock'), fn ($builder) => $builder->whereColumn('stock_actual', '<=', 'stock_minimo'))
            ->when($request->boolean('expired'), fn ($builder) => $builder->whereNotNull('fecha_vencimiento')->whereDate('fecha_vencimiento', '<', now()->toDateString()))
            ->when($request->boolean('expiring'), fn ($builder) => $builder->whereNotNull('fecha_vencimiento')->whereDate('fecha_vencimiento', '>=', now()->toDateString())->whereDate('fecha_vencimiento', '<=', now()->addDays(30)->toDateString()))
            ->when($request->boolean('without_price'), function ($builder) {
                $builder->where(function ($inner) {
                    $inner->where('precio_venta', '<=', 0)
                        ->orWhereHas('variantes', fn ($v) => $v->whereNull('sale_price')->orWhere('sale_price', '<=', 0));
                });
            })
            ->when($request->boolean('needs_review'), function ($builder) {
                $builder->whereHas('variantes', fn ($v) => $v->where('needs_review', true));
            });
    }

    private function paginateGrouped(Builder $query, Request $request, int $perPage): LengthAwarePaginator
    {
        $groupColumns = [
            'productos.nombre',
            'productos.categoria_id',
            'productos.marca_id',
            'productos.especie',
        ];

        $total = (int) DB::query()
            ->fromSub(
                $query->clone()->select($groupColumns)->groupBy($groupColumns),
                'product_groups'
            )
            ->count();

        $page = max(1, (int) $request->query('page', 1));

        $rows = $query->clone()
            ->select([
                DB::raw('MIN(productos.id) as id'),
                'productos.nombre',
                'productos.categoria_id',
                'productos.marca_id',
                'productos.especie',
                DB::raw('SUM((SELECT COALESCE(SUM(b.quantity), 0) FROM variantes v INNER JOIN batches b ON b.variant_id = v.id WHERE v.producto_id = productos.id)) as stock_actual'),
                DB::raw('MIN(productos.stock_minimo) as stock_minimo'),
                DB::raw('MAX(productos.precio_venta) as precio_venta'),
                DB::raw('MAX(productos.precio_compra) as precio_compra'),
                DB::raw('MAX(productos.activo) as activo'),
                DB::raw('COUNT(*) as variant_count'),
                DB::raw("GROUP_CONCAT(DISTINCT NULLIF(productos.talla, '') ORDER BY productos.talla SEPARATOR ', ') as tallas"),
                DB::raw("GROUP_CONCAT(DISTINCT NULLIF(productos.presentacion, '') ORDER BY productos.presentacion SEPARATOR ', ') as presentaciones"),
            ])
            ->groupBy($groupColumns)
            ->orderBy('productos.nombre')
            ->forPage($page, $perPage)
            ->get();

        $models = Producto::query()
            ->with(['categoria', 'marca', 'proveedor', 'variantes.batches'])
            ->whereIn('id', $rows->pluck('id')->filter()->all())
            ->get()
            ->keyBy('id');

        $items = $rows->map(function ($row) use ($models) {
            $producto = $models->get($row->id) ?? new Producto();
            $producto->setAttribute('stock_actual', $row->stock_actual);
            $producto->setAttribute('stock_minimo', $row->stock_minimo);
            $producto->setAttribute('precio_venta', $row->precio_venta);
            $producto->setAttribute('precio_compra', $row->precio_compra);
            $producto->setAttribute('activo', (bool) $row->activo);
            $producto->setAttribute('variant_count', (int) $row->variant_count);
            $producto->setAttribute('tallas', $row->tallas);
            $producto->setAttribute('presentaciones', $row->presentaciones);
            $producto->setAttribute('talla', $row->tallas);
            $producto->setAttribute('presentacion', $row->presentaciones);

            return $producto;
        });

        return new Paginator($items, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyCommercialDataToGroup(Producto $producto, array $data): void
    {
        $shared = array_intersect_key($data, array_flip([
            'categoria_id',
            'marca_id',
            'especie',
            'tipo',
            'precio_compra',
            'precio_venta',
            'unidad_medida',
            'activo',
        ]));
        if ($shared === []) {
            $shared = [
                'precio_venta' => $producto->precio_venta,
                'precio_compra' => $producto->precio_compra,
            ];
        }

        $siblings = Producto::query()
            ->where('nombre', $producto->nombre)
            ->where('categoria_id', $producto->categoria_id)
            ->where('id', '!=', $producto->id)
            ->get();

        foreach ($siblings as $sibling) {
            $sibling->update($shared);
            $this->syncPrimaryVariant($sibling->fresh(), $shared);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPrimaryVariant(Producto $producto, array $data): void
    {
        $salePrice = (float) $producto->precio_venta;
        $pricePayload = [
            'cost_price' => $producto->precio_compra,
            'sale_price' => $salePrice > 0 ? $producto->precio_venta : null,
            'min_stock' => (int) $producto->stock_minimo,
            'activo' => (bool) $producto->activo,
        ];
        if ($salePrice > 0) {
            $pricePayload['needs_review'] = false;
        }

        $variants = $producto->variantes()->orderBy('id')->get();
        if ($variants->isNotEmpty()) {
            foreach ($variants as $index => $variant) {
                $payload = $pricePayload;
                if ($index === 0) {
                    $payload['sku'] = $variant->sku ?: $producto->codigo;
                    $payload['presentation'] = $producto->presentacion ?: $producto->talla ?: $variant->presentation;
                    $payload['unit'] = $producto->unidad_medida ?: ($variant->unit ?: 'unidad');
                    $payload['sale_unit'] = $producto->unidad_medida ?: ($variant->sale_unit ?: 'unidad');
                }
                $variant->update($payload);
            }

            return;
        }

        $variant = $producto->variantes()->create($pricePayload + [
            'sku' => $producto->codigo,
            'presentation' => $producto->presentacion ?: $producto->talla,
            'unit' => $producto->unidad_medida ?: 'unidad',
            'sale_unit' => $producto->unidad_medida ?: 'unidad',
        ]);
        $qty = (int) ($data['stock_actual'] ?? $producto->stock_actual ?? 0);
        if ($qty > 0) {
            Batch::query()->create([
                'variant_id' => $variant->id,
                'batch_code' => 'INI-'.$producto->codigo,
                'expiry_date' => $producto->fecha_vencimiento,
                'quantity' => $qty,
                'cost' => $producto->precio_compra,
            ]);
        }
    }
}
