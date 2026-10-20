<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    // Listar todos los productos
    public function index()
    {
        $lotes = $this->stockLotes();
        $productos = Producto::with(['categoria', 'proveedor'])
            ->orderBy('nombre')
            ->get()
            ->map(fn ($producto) => $this->format($producto, $lotes));

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
        ]);

        $producto = Producto::create($request->all());

        return response()->json([
            'message' => 'Producto registrado exitosamente',
            'producto' => $producto->load(['categoria', 'proveedor']),
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

        foreach (['codigo', 'descripcion', 'unidad_medida', 'fecha_vencimiento', 'categoria_id', 'proveedor_id'] as $campo) {
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
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn ($producto) => $this->format($producto, $this->stockLotes()));

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
            ->map(fn ($producto) => $this->format($producto, $this->stockLotes()));

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

    private function format(Producto $producto, array $lotes = []): array
    {
        $enLotes = (int) ($lotes[$producto->id] ?? 0);
        $stockDisponible = $enLotes > 0 ? $enLotes : (int) $producto->stock_actual;

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
            'stock_actual' => $producto->stock_actual,
            'stock_disponible' => $stockDisponible,
            'stock_minimo' => $producto->stock_minimo,
            'stock_bajo' => $producto->stockBajo(),
            'unidad_medida' => $producto->unidad_medida,
            'fecha_vencimiento' => $producto->fecha_vencimiento?->format('Y-m-d'),
            'requiere_receta' => $producto->requiere_receta,
            'activo' => $producto->activo,
        ];
    }
}