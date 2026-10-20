<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MovimientoStock;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovimientoStockController extends Controller
{
    // Listar movimientos (con filtros opcionales)
    public function index(Request $request)
    {
        $query = MovimientoStock::with(['producto:id,nombre,codigo', 'user:id,name'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('producto_id')) {
            $query->where('producto_id', $request->producto_id);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->hasta);
        }

        $movimientos = $query->get();

        return response()->json([
            'message'      => 'Movimientos obtenidos exitosamente',
            'total'        => $movimientos->count(),
            'movimientos'  => $movimientos,
        ]);
    }

    // Registrar nuevo movimiento
    public function store(Request $request)
    {
        $request->validate([
            'producto_id'    => 'required|exists:productos,id',
            'tipo'           => 'required|in:entrada,salida,ajuste',
            'cantidad'       => 'required|integer|min:1',
            'precio_unitario' => 'nullable|numeric|min:0',
            'motivo'         => 'nullable|string|max:255',
            'referencia'     => 'nullable|string|max:255',
        ]);

        $producto = Producto::findOrFail($request->producto_id);
        $stockAnterior = $producto->stock_actual;

        // Calcular nuevo stock según tipo
        $stockNuevo = match ($request->tipo) {
            'entrada' => $stockAnterior + $request->cantidad,
            'salida'  => $stockAnterior - $request->cantidad,
            'ajuste'  => $request->cantidad, // ajuste directo al valor indicado
        };

        if ($stockNuevo < 0) {
            return response()->json([
                'message' => "Stock insuficiente. Stock actual: {$stockAnterior}, cantidad solicitada: {$request->cantidad}",
            ], 422);
        }

        $movimiento = DB::transaction(function () use ($request, $producto, $stockAnterior, $stockNuevo) {
            $mov = MovimientoStock::create([
                'producto_id'    => $producto->id,
                'user_id'        => auth()->id(),
                'tipo'           => $request->tipo,
                'cantidad'       => $request->tipo === 'ajuste'
                                        ? abs($stockNuevo - $stockAnterior)
                                        : $request->cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $stockNuevo,
                'precio_unitario' => $request->precio_unitario,
                'motivo'         => $request->motivo,
                'referencia'     => $request->referencia,
            ]);

            $producto->update(['stock_actual' => $stockNuevo]);

            return $mov;
        });

        return response()->json([
            'message'         => 'Movimiento registrado exitosamente',
            'movimiento'      => $movimiento->load(['producto:id,nombre,codigo', 'user:id,name']),
            'stock_anterior'  => $stockAnterior,
            'stock_nuevo'     => $stockNuevo,
        ], 201);
    }

    // Ver un movimiento
    public function show($id)
    {
        $movimiento = MovimientoStock::with(['producto:id,nombre,codigo', 'user:id,name'])->find($id);

        if (!$movimiento) {
            return response()->json(['message' => 'Movimiento no encontrado'], 404);
        }

        return response()->json(['movimiento' => $movimiento]);
    }

    // Historial de un producto específico
    public function porProducto($id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $movimientos = MovimientoStock::with('user:id,name')
            ->where('producto_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'producto'     => ['id' => $producto->id, 'nombre' => $producto->nombre, 'stock_actual' => $producto->stock_actual],
            'total'        => $movimientos->count(),
            'movimientos'  => $movimientos,
        ]);
    }
}
