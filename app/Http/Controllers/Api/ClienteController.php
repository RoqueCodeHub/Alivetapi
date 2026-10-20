<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteController extends Controller
{
    public function index()
    {
        $clientes = Cliente::with('persona')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn($c) => $this->format($c));

        return response()->json([
            'message'  => 'Clientes obtenidos exitosamente',
            'total'    => $clientes->count(),
            'clientes' => $clientes,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombres'          => 'required|string|max:255',
            'apellidos'        => 'required|string|max:255',
            'tipo_documento'   => 'required|in:DNI,CE,PASAPORTE',
            'numero_documento' => 'nullable|string|unique:personas,numero_documento',
            'telefono'         => 'nullable|string|max:50',
            'email'            => 'nullable|email|unique:personas,email',
            'direccion'        => 'nullable|string',
            'observaciones'    => 'nullable|string',
        ]);

        $cliente = DB::transaction(function () use ($request) {
            $persona = Persona::create($request->only([
                'nombres', 'apellidos', 'tipo_documento', 'numero_documento',
                'telefono', 'email', 'direccion',
            ]));

            return Cliente::create([
                'persona_id'    => $persona->id,
                'observaciones' => $request->observaciones,
                'activo'        => true,
            ]);
        });

        return response()->json([
            'message' => 'Cliente registrado exitosamente',
            'cliente' => $this->format($cliente->load('persona')),
        ], 201);
    }

    public function show($id)
    {
        $cliente = Cliente::with('persona')->withCount('ventas')->find($id);

        if (!$cliente) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        return response()->json([
            'cliente'      => $this->format($cliente),
            'total_ventas' => $cliente->ventas_count,
        ]);
    }

    public function update(Request $request, $id)
    {
        $cliente = Cliente::with('persona')->find($id);

        if (!$cliente) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        $request->validate([
            'nombres'          => 'sometimes|string|max:255',
            'apellidos'        => 'sometimes|string|max:255',
            'tipo_documento'   => 'sometimes|in:DNI,CE,PASAPORTE',
            'numero_documento' => 'nullable|string|unique:personas,numero_documento,' . $cliente->persona_id,
            'telefono'         => 'nullable|string|max:50',
            'email'            => 'nullable|email|unique:personas,email,' . $cliente->persona_id,
            'direccion'        => 'nullable|string',
            'activo'           => 'boolean',
            'observaciones'    => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $cliente) {
            $cliente->persona->update($request->only([
                'nombres', 'apellidos', 'tipo_documento', 'numero_documento',
                'telefono', 'email', 'direccion', 'activo',
            ]));

            $cliente->update($request->only(['observaciones', 'activo']));
        });

        return response()->json([
            'message' => 'Cliente actualizado exitosamente',
            'cliente' => $this->format($cliente->load('persona')),
        ]);
    }

    public function destroy($id)
    {
        $cliente = Cliente::with('persona')->find($id);

        if (!$cliente) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        if ($cliente->ventas()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el cliente porque tiene ventas registradas.',
            ], 409);
        }

        DB::transaction(function () use ($cliente) {
            $persona = $cliente->persona;
            $cliente->delete();
            $persona->delete();
        });

        return response()->json(['message' => 'Cliente eliminado exitosamente']);
    }

    private function format(Cliente $c): array
    {
        return [
            'id'               => $c->id,
            'nombres'          => optional($c->persona)->nombres,
            'apellidos'        => optional($c->persona)->apellidos,
            'nombre_completo'  => optional($c->persona)->nombre_completo,
            'tipo_documento'   => optional($c->persona)->tipo_documento,
            'numero_documento' => optional($c->persona)->numero_documento,
            'telefono'         => optional($c->persona)->telefono,
            'email'            => optional($c->persona)->email,
            'direccion'        => optional($c->persona)->direccion,
            'activo'           => (bool) ($c->activo ?? optional($c->persona)->activo),
            'observaciones'    => $c->observaciones,
        ];
    }
}
