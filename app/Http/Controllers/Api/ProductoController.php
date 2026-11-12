<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    // Listar todos los productos
    public function index()
    {
        $lotes = $this->stockLotes();
        $presentaciones = $this->presentaciones();
        $productos = Producto::with(['categoria', 'proveedor'])
            ->orderBy('nombre')
            ->get()
            ->map(fn ($producto) => $this->format($producto, $lotes, $presentaciones));

        return response()->json([
            'message' => 'Productos obtenidos exitosamente',
            'total' => $productos->count(),
            'productos' => $productos,
        ]);
    }

    // Registrar nuevo producto
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'codigo' => 'nullable|string|unique:productos,codigo',
            'categoria_id' => 'required|exists:categorias,id',
            'proveedor_id' => 'required|exists:proveedores,id',
            'precio_compra' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'stock_actual' => 'required|integer|min:0',
            'stock_minimo' => 'required|integer|min:0',
            'unidad_medida' => 'required|string',
            'fecha_vencimiento' => 'nullable|date|after:today',
            'requiere_receta' => 'boolean',
            'principio_activo' => 'nullable|string|max:150',
            'concentracion' => 'nullable|string|max:80',
            'tipo_receta' => 'nullable|in:OTC,RECETA_SIMPLE,CONTROLADO',
            'especies_destino' => 'nullable|array',
            'especies_destino.*' => 'string|max:40',
            'cadena_frio' => 'boolean',
            'tiempo_retiro_dias' => 'nullable|integer|min:0|max:3650',
            'permite_fraccionamiento' => 'boolean',
            'unidad_dispensacion' => 'nullable|string|max:50',
            'factor_conversion' => 'nullable|numeric|min:0',
            'precio_venta_dosis' => 'nullable|numeric|min:0',
            'codigo_lote' => 'nullable|string|max:80',
        ]);

        $producto = Producto::create($request->all());
        app(StockService::class)->materializeColumnStock($producto);
        $producto->syncStockActual();

        return response()->json([
            'message' => 'Producto registrado exitosamente',
            'producto' => $this->format($producto->load(['categoria', 'proveedor']), $this->stockLotes(), $this->presentaciones()),
        ], 201);
    }

    // Ver un producto
    public function show($id)
    {
        $producto = Producto::with(['categoria', 'proveedor'])->find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        return response()->json([
            'producto' => $producto,
            'stock_bajo' => $producto->stockBajo(),
        ]);
    }

    // Actualizar producto
    public function update(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        foreach (['codigo', 'descripcion', 'unidad_medida', 'fecha_vencimiento', 'categoria_id', 'proveedor_id', 'principio_activo', 'concentracion', 'unidad_dispensacion', 'codigo_lote'] as $campo) {
            if ($request->exists($campo) && $request->input($campo) === '') {
                $request->merge([$campo => null]);
            }
        }

        $datos = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'codigo' => ['nullable', 'string', 'max:255', Rule::unique('productos', 'codigo')->ignore($producto->id)],
            'categoria_id' => 'nullable|exists:categorias,id',
            'proveedor_id' => 'nullable|exists:proveedores,id',
            'precio_compra' => 'sometimes|numeric|min:0',
            'precio_venta' => 'sometimes|numeric|min:0',
            'stock_minimo' => 'sometimes|numeric|min:0',
            'unidad_medida' => 'nullable|string|max:50',
            'fecha_vencimiento' => 'nullable|date',
            'requiere_receta' => 'sometimes|boolean',
            'activo' => 'sometimes|boolean',
            'principio_activo' => 'nullable|string|max:150',
            'concentracion' => 'nullable|string|max:80',
            'tipo_receta' => 'nullable|in:OTC,RECETA_SIMPLE,CONTROLADO',
            'especies_destino' => 'nullable|array',
            'especies_destino.*' => 'string|max:40',
            'cadena_frio' => 'sometimes|boolean',
            'tiempo_retiro_dias' => 'nullable|integer|min:0|max:3650',
            'permite_fraccionamiento' => 'sometimes|boolean',
            'unidad_dispensacion' => 'nullable|string|max:50',
            'factor_conversion' => 'nullable|numeric|min:0',
            'precio_venta_dosis' => 'nullable|numeric|min:0',
            'codigo_lote' => 'nullable|string|max:80',
        ]);

        $producto->update($datos);

        return response()->json([
            'message' => 'Producto actualizado exitosamente',
            'producto' => $producto->load(['categoria', 'proveedor']),
        ]);
    }

    // Eliminar producto
    public function destroy($id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $producto->delete();

        return response()->json(['message' => 'Producto eliminado exitosamente']);
    }

    // Productos con stock bajo
    public function stockBajo()
    {
        $productos = Producto::with(['categoria', 'proveedor'])
            ->whereRaw('(SELECT COALESCE(SUM(b.quantity), 0) FROM variantes v INNER JOIN batches b ON b.variant_id = v.id WHERE v.producto_id = productos.id) <= stock_minimo')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn ($producto) => $this->format($producto, $this->stockLotes(), $this->presentaciones()));

        return response()->json([
            'message' => 'Productos con stock bajo',
            'total' => $productos->count(),
            'productos' => $productos,
        ]);
    }

    // Productos por vencer (próximos 30 días)
    public function porVencer()
    {
        $productos = Producto::with(['categoria', 'proveedor'])
            ->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [now(), now()->addDays(30)])
            ->where('activo', true)
            ->orderBy('fecha_vencimiento')
            ->get()
            ->map(fn ($producto) => $this->format($producto, $this->stockLotes(), $this->presentaciones()));

        return response()->json([
            'message' => 'Productos próximos a vencer',
            'total' => $productos->count(),
            'productos' => $productos,
        ]);
    }

    private function stockLotes(): array
    {
        return DB::table('batches')
            ->join('variantes', 'variantes.id', '=', 'batches.variant_id')
            ->groupBy('variantes.producto_id')
            ->selectRaw('variantes.producto_id as producto_id, SUM(batches.quantity) as stock')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->producto_id => (int) $row->stock])
            ->all();
    }

    private function presentaciones(): array
    {
        return DB::table('variantes')
            ->leftJoin('batches', 'batches.variant_id', '=', 'variantes.id')
            ->groupBy('variantes.id', 'variantes.producto_id', 'variantes.presentation', 'variantes.sku', 'variantes.unit')
            ->orderBy('variantes.id')
            ->selectRaw('variantes.id as id, variantes.producto_id as producto_id, variantes.presentation as presentation, variantes.sku as sku, variantes.unit as unit, COALESCE(SUM(batches.quantity), 0) as stock')
            ->get()
            ->groupBy(fn ($row) => (int) $row->producto_id)
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'presentacion' => $row->presentation ?: ($row->unit ?: '—'),
                'sku' => $row->sku,
                'stock' => (int) $row->stock,
            ])->values()->all())
            ->all();
    }

    private function format(Producto $producto, array $lotes = [], array $presentaciones = []): array
    {
        $enLotes = (int) ($lotes[$producto->id] ?? 0);
        $stockDisponible = array_key_exists($producto->id, $lotes)
            ? $enLotes
            : (int) $producto->stock_actual;

        return [
            'id' => $producto->id,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion,
            'codigo' => $producto->codigo,
            'categoria_id' => $producto->categoria_id,
            'categoria' => $producto->categoria?->nombre,
            'proveedor' => $producto->proveedor ? [
                'id' => $producto->proveedor->id,
                'nombre' => $producto->proveedor->displayName(),
            ] : null,
            'precio_compra' => $producto->precio_compra,
            'precio_venta' => $producto->precio_venta,
            'stock_actual' => $stockDisponible,
            'stock_disponible' => $stockDisponible,
            'presentaciones' => $presentaciones[$producto->id] ?? [],
            'stock_minimo' => $producto->stock_minimo,
            'stock_bajo' => $stockDisponible <= (int) $producto->stock_minimo,
            'unidad_medida' => $producto->unidad_medida,
            'fecha_vencimiento' => $producto->fecha_vencimiento?->format('Y-m-d'),
            'requiere_receta' => $producto->requiere_receta,
            'activo' => $producto->activo,
            'principio_activo' => $producto->principio_activo,
            'concentracion' => $producto->concentracion,
            'tipo_receta' => $producto->tipo_receta,
            'especies_destino' => $producto->especies_destino ?? [],
            'cadena_frio' => (bool) $producto->cadena_frio,
            'tiempo_retiro_dias' => $producto->tiempo_retiro_dias,
            'permite_fraccionamiento' => (bool) $producto->permite_fraccionamiento,
            'unidad_dispensacion' => $producto->unidad_dispensacion,
            'factor_conversion' => $producto->factor_conversion,
            'precio_venta_dosis' => $producto->precio_venta_dosis,
            'codigo_lote' => $producto->codigo_lote,
        ];
    }
}