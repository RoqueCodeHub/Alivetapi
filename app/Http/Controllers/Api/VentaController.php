<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Venta;
use App\Models\Producto;
use App\Models\MovimientoStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VentaController extends Controller
{
    // Listar ventas con filtros opcionales
    public function index(Request $request)
    {
        $query = Venta::with([
            'cliente.persona:id,nombres,apellidos',
            'trabajador.persona:id,nombres,apellidos',
            'detalles.producto:id,nombre,codigo',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->filled('trabajador_id')) {
            $query->where('trabajador_id', $request->trabajador_id);
        }

        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->hasta);
        }

        $ventas = $query->get()->map(fn($v) => $this->format($v));

        return response()->json([
            'message' => 'Ventas obtenidas exitosamente',
            'total'   => $ventas->count(),
            'ventas'  => $ventas,
        ]);
    }

    // Registrar nueva venta
    public function store(Request $request)
    {
        $request->validate([
            'cliente_id'          => 'required|exists:clientes,id',
            'trabajador_id'       => 'nullable|exists:trabajadores,id',
            'descuento'           => 'nullable|numeric|min:0',
            'observaciones'       => 'nullable|string',
            'metodo_pago'         => 'nullable|in:efectivo,yape,plin,tarjeta,transferencia',
            'monto_pagado'        => 'nullable|numeric|min:0',
            'items'               => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad'    => 'required|integer|min:1',
            'items.*.precio_unitario' => 'nullable|numeric|min:0',
        ]);

        foreach ($request->items as $item) {
            $producto = Producto::find($item['producto_id']);
            $disponible = $this->disponible($producto);
            if ($disponible < $item['cantidad']) {
                return response()->json([
                    'message' => "Stock insuficiente para \"{$producto->nombre}\". Stock actual: {$disponible}, solicitado: {$item['cantidad']}",
                ], 422);
            }
        }

        $cobraAhora = $request->filled('metodo_pago');
        if ($cobraAhora && ! $this->puedeCobrar()) {
            return response()->json([
                'message' => 'El operador solo registra la venta. El cobro lo hace la cajera o el administrador.',
            ], 403);
        }

        try {
            $venta = DB::transaction(function () use ($request, $cobraAhora) {
                $subtotalGeneral = 0;
                $lineas = [];

                foreach ($request->items as $item) {
                    $producto = Producto::find($item['producto_id']);
                    $precioUnitario = $item['precio_unitario'] ?? $producto->precio_venta;
                    $subtotal = $precioUnitario * $item['cantidad'];
                    $subtotalGeneral += $subtotal;
                    $lineas[] = compact('producto', 'item', 'precioUnitario', 'subtotal');
                }

                $descuento = (float) ($request->descuento ?? 0);
                $total = max(0, round($subtotalGeneral - $descuento, 2));
                $montoPagado = $cobraAhora ? round((float) $request->monto_pagado, 2) : null;
                if ($montoPagado === null || $montoPagado <= 0) {
                    $montoPagado = $cobraAhora ? $total : null;
                }

                if ($cobraAhora && $montoPagado + 0.001 < $total) {
                    throw new RuntimeException('El monto recibido no cubre el total de la venta.');
                }

                $venta = Venta::create([
                    'cliente_id'    => $request->cliente_id,
                    'trabajador_id' => $request->trabajador_id,
                    'descuento'     => $descuento,
                    'total'         => $total,
                    'estado'        => $cobraAhora ? 'completada' : 'pendiente',
                    'metodo_pago'   => $cobraAhora ? $request->metodo_pago : null,
                    'monto_pagado'  => $montoPagado,
                    'observaciones' => $request->observaciones,
                ]);

                foreach ($lineas as $linea) {
                    $producto = $linea['producto'];
                    $item = $linea['item'];
                    $anterior = $this->disponible($producto);

                    $venta->detalles()->create([
                        'producto_id'     => $producto->id,
                        'cantidad'        => $item['cantidad'],
                        'precio_unitario' => $linea['precioUnitario'],
                        'subtotal'        => $linea['subtotal'],
                    ]);

                    if ($cobraAhora) {
                        $this->registrarSalida($venta, $producto, (int) $item['cantidad'], $linea['precioUnitario'], $anterior);
                    }
                }

                return $venta;
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Venta registrada exitosamente',
            'venta'   => $this->format($venta->load([
                'cliente.persona:id,nombres,apellidos',
                'trabajador.persona:id,nombres,apellidos',
                'detalles.producto:id,nombre,codigo',
            ])),
        ], 201);
    }

    public function cobrar(Request $request, $id)
    {
        if (! $this->puedeCobrar()) {
            return response()->json([
                'message' => 'Solo la cajera o el administrador pueden cobrar.',
            ], 403);
        }

        $request->validate([
            'metodo_pago'  => 'required|in:efectivo,yape,plin,tarjeta,transferencia',
            'monto_pagado' => 'required|numeric|min:0',
        ]);

        $venta = Venta::with([
            'cliente.persona:id,nombres,apellidos',
            'trabajador.persona:id,nombres,apellidos',
            'detalles.producto:id,nombre,codigo',
        ])->find($id);

        if (! $venta) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }

        if ($venta->estado !== 'pendiente') {
            return response()->json(['message' => 'Esta venta ya no está pendiente de cobro.'], 422);
        }

        $monto = round((float) $request->monto_pagado, 2);
        $total = round((float) $venta->total, 2);
        if ($monto + 0.001 < $total) {
            return response()->json(['message' => 'El monto recibido no cubre el total de la venta.'], 422);
        }

        try {
            DB::transaction(function () use ($venta, $request, $monto) {
                $venta->load('detalles.producto');
                if (! $this->yaDesconto($venta)) {
                    foreach ($venta->detalles as $detalle) {
                        $producto = $detalle->producto;
                        if (! $producto) {
                            continue;
                        }
                        $disponible = $this->disponible($producto);
                        if ($disponible < (int) $detalle->cantidad) {
                            throw new RuntimeException("Stock insuficiente para \"{$producto->nombre}\". Stock actual: {$disponible}, solicitado: {$detalle->cantidad}");
                        }
                    }
                    foreach ($venta->detalles as $detalle) {
                        $producto = $detalle->producto;
                        if (! $producto) {
                            continue;
                        }
                        $anterior = $this->disponible($producto);
                        $this->registrarSalida($venta, $producto, (int) $detalle->cantidad, $detalle->precio_unitario, $anterior);
                    }
                }

                $venta->update([
                    'estado'       => 'completada',
                    'metodo_pago'  => $request->metodo_pago,
                    'monto_pagado' => $monto,
                ]);
            });
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Cobranza registrada',
            'venta'   => $this->format($venta->fresh([
                'cliente.persona:id,nombres,apellidos',
                'trabajador.persona:id,nombres,apellidos',
                'detalles.producto:id,nombre,codigo',
            ])),
        ]);
    }

    // Ver una venta
    public function show($id)
    {
        $venta = Venta::with([
            'cliente.persona:id,nombres,apellidos',
            'trabajador.persona:id,nombres,apellidos',
            'detalles.producto:id,nombre,codigo,unidad_medida',
        ])->find($id);

        if (!$venta) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }

        return response()->json(['venta' => $this->format($venta)]);
    }

    // Anular venta (revierte stock)
    public function anular($id)
    {
        $venta = Venta::with('detalles.producto')->find($id);

        if (!$venta) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }

        if ($venta->estado === 'anulada') {
            return response()->json(['message' => 'La venta ya está anulada'], 422);
        }

        $reponer = $venta->estado === 'completada' || $this->yaDesconto($venta);

        DB::transaction(function () use ($venta, $reponer) {
            if ($reponer) {
                foreach ($venta->detalles as $detalle) {
                    $producto = $detalle->producto;
                    if (!$producto) {
                        continue;
                    }

                    $stockAnterior = $this->disponible($producto);
                    $this->reponerStock($producto, (int) $detalle->cantidad);

                    MovimientoStock::create([
                        'producto_id'     => $producto->id,
                        'user_id'         => auth('api')->id() ?? auth()->id(),
                        'tipo'            => 'entrada',
                        'cantidad'        => $detalle->cantidad,
                        'stock_anterior'  => $stockAnterior,
                        'stock_nuevo'     => $stockAnterior + $detalle->cantidad,
                        'precio_unitario' => $detalle->precio_unitario,
                        'motivo'          => 'anulacion_venta',
                        'referencia'      => "anulacion_venta#{$venta->id}",
                    ]);
                }
            }

            $venta->update(['estado' => 'anulada']);
        });

        $message = $reponer
            ? 'Venta anulada y stock revertido exitosamente'
            : 'Venta cancelada. El stock no se había movido.';

        return response()->json(['message' => $message]);
    }

    // Helper: formato de respuesta
    private function format(Venta $v): array
    {
        return [
            'id'           => $v->id,
            'cliente'      => $v->cliente ? [
                'id'             => $v->cliente->id,
                'nombre_completo' => $v->cliente->displayName(),
            ] : null,
            'trabajador'   => $v->trabajador ? [
                'id'             => $v->trabajador->id,
                'nombre_completo' => optional($v->trabajador->persona)->nombres . ' ' . optional($v->trabajador->persona)->apellidos,
            ] : null,
            'items'        => $v->detalles->map(fn($d) => [
                'producto_id'    => $d->producto_id,
                'producto'       => $d->producto ? [
                    'id'     => $d->producto->id,
                    'nombre' => $d->producto->nombre,
                ] : null,
                'cantidad'       => $d->cantidad,
                'precio_unitario' => $d->precio_unitario,
                'subtotal'       => $d->subtotal,
            ]),
            'descuento'    => $v->descuento,
            'total'        => $v->total,
            'estado'       => $v->estado,
            'metodo_pago'  => $v->metodo_pago,
            'monto_pagado' => $v->monto_pagado,
            'observaciones' => $v->observaciones,
            'fecha'        => $v->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function yaDesconto(Venta $venta): bool
    {
        return MovimientoStock::query()
            ->where('referencia', "venta#{$venta->id}")
            ->where('tipo', 'salida')
            ->exists();
    }

    private function registrarSalida(Venta $venta, Producto $producto, int $cantidad, $precio, int $anterior): void
    {
        $this->descontarStock($producto, $cantidad);

        MovimientoStock::create([
            'producto_id'     => $producto->id,
            'user_id'         => auth('api')->id() ?? auth()->id(),
            'tipo'            => 'salida',
            'cantidad'        => $cantidad,
            'stock_anterior'  => $anterior,
            'stock_nuevo'     => $anterior - $cantidad,
            'precio_unitario' => $precio,
            'motivo'          => 'venta',
            'referencia'      => "venta#{$venta->id}",
        ]);
    }

    private function puedeCobrar(): bool
    {
        $user = auth('api')->user();

        return $user && $user->hasAnyRole(['Administrador', 'Cajero']);
    }

    private function disponible(Producto $producto): int
    {
        $lotes = (int) DB::table('batches')
            ->join('variantes', 'variantes.id', '=', 'batches.variant_id')
            ->where('variantes.producto_id', $producto->id)
            ->sum('batches.quantity');

        return $lotes > 0 ? $lotes : (int) $producto->stock_actual;
    }

    private function descontarStock(Producto $producto, int $cantidad): void
    {
        $restante = $cantidad;
        $lotes = Batch::query()
            ->whereIn('variant_id', function ($query) use ($producto) {
                $query->select('id')->from('variantes')->where('producto_id', $producto->id);
            })
            ->where('quantity', '>', 0)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->lockForUpdate()
            ->get();

        foreach ($lotes as $lote) {
            if ($restante <= 0) {
                break;
            }
            $tomar = min($restante, (int) $lote->quantity);
            $lote->decrement('quantity', $tomar);
            $restante -= $tomar;
        }

        if ($restante > 0) {
            $producto->refresh();
            if ((int) $producto->stock_actual < $restante) {
                throw new RuntimeException("Stock insuficiente para \"{$producto->nombre}\".");
            }
            $producto->decrement('stock_actual', $restante);
        }
    }

    private function reponerStock(Producto $producto, int $cantidad): void
    {
        $lote = Batch::query()
            ->whereIn('variant_id', function ($query) use ($producto) {
                $query->select('id')->from('variantes')->where('producto_id', $producto->id);
            })
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->lockForUpdate()
            ->first();

        if ($lote) {
            $lote->increment('quantity', $cantidad);
            return;
        }

        $producto->increment('stock_actual', $cantidad);
    }
}
