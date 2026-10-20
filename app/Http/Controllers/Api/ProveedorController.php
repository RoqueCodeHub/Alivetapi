<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProveedorController extends Controller
{
    // Listar todos los proveedores
    public function index()
    {
        $proveedores = Proveedor::with('persona')
            ->orderBy('id')
            ->get()
            ->map(fn($p) => $this->format($p));

        return response()->json([
            'message'     => 'Proveedores obtenidos exitosamente',
            'total'       => $proveedores->count(),
            'proveedores' => $proveedores,
        ]);
    }

    // Registrar nuevo proveedor
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
            'activo'           => 'boolean',
            'empresa'          => 'nullable|string|max:255',
        ]);

        $proveedor = DB::transaction(function () use ($request) {
            $persona = Persona::create($request->only([
                'nombres', 'apellidos', 'tipo_documento', 'numero_documento',
                'telefono', 'email', 'direccion', 'activo',
            ]));

            return Proveedor::create([
                'persona_id' => $persona->id,
                'empresa'    => $request->empresa,
            ]);
        });

        return response()->json([
            'message'   => 'Proveedor registrado exitosamente',
            'proveedor' => $this->format($proveedor->load('persona')),
        ], 201);
    }

    // Ver un proveedor (con sus productos)
    public function show($id)
    {
        $proveedor = Proveedor::with(['persona', 'productos'])->find($id);

        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        return response()->json(['proveedor' => $this->format($proveedor, true)]);
    }

    // Actualizar proveedor
    public function update(Request $request, $id)
    {
        $proveedor = Proveedor::with('persona')->find($id);

        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        $request->merge([
            'email' => $request->input('email') ?: null,
            'telefono' => $request->input('telefono') ?: null,
            'numero_documento' => $request->input('numero_documento') ?: null,
            'apellidos' => $request->input('apellidos') ?: null,
        ]);

        $documentoRules = ['nullable', 'string', 'max:20'];
        if (! $proveedor->persona || $request->input('tipo_documento') === 'RUC') {
            $documentoRules[] = Rule::unique('proveedores', 'ruc')->ignore($proveedor->id);
        }

        $request->validate([
            'nombres'          => 'sometimes|nullable|string|max:255',
            'apellidos'        => 'sometimes|nullable|string|max:255',
            'tipo_documento'   => 'sometimes|nullable|in:DNI,RUC,CE,PASAPORTE,Pasaporte',
            'numero_documento' => $documentoRules,
            'telefono'         => 'nullable|string|max:50',
            'email'            => 'nullable|email|max:255',
            'direccion'        => 'nullable|string',
            'activo'           => 'sometimes|boolean',
            'empresa'          => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $proveedor) {
            if ($proveedor->persona) {
                $proveedor->persona->update($request->only([
                    'nombres', 'apellidos', 'tipo_documento', 'numero_documento',
                    'telefono', 'email', 'direccion', 'activo',
                ]));
            }

            $nombre = trim((string) $request->input('nombres', '').' '.(string) $request->input('apellidos', ''));
            if ($nombre !== '') {
                $proveedor->nombre = $nombre;
            }
            if ($request->exists('empresa')) {
                $proveedor->empresa = $request->input('empresa');
            }
            if ($request->exists('telefono')) {
                $proveedor->telefono = $request->input('telefono');
            }
            if ($request->exists('email')) {
                $proveedor->email = $request->input('email');
            }
            if ($request->exists('activo')) {
                $proveedor->activo = $request->boolean('activo');
            }
            if ($request->input('tipo_documento') === 'RUC' || ! $proveedor->persona) {
                $proveedor->ruc = $request->input('numero_documento');
            }

            $proveedor->save();
        });

        return response()->json([
            'message'   => 'Proveedor actualizado exitosamente',
            'proveedor' => $this->format($proveedor->load('persona')),
        ]);
    }

    // Eliminar proveedor
    public function destroy($id)
    {
        $proveedor = Proveedor::with('persona')->find($id);

        if (!$proveedor) {
            return response()->json(['message' => 'Proveedor no encontrado'], 404);
        }

        if ($proveedor->productos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el proveedor porque tiene productos asociados',
            ], 409);
        }

        DB::transaction(function () use ($proveedor) {
            $persona = $proveedor->persona;
            $proveedor->delete();
            $persona?->delete();
        });

        return response()->json(['message' => 'Proveedor eliminado exitosamente']);
    }

    // Helper: formato de respuesta
    private function format(Proveedor $p, bool $conProductos = false): array
    {
        $persona = $p->persona;
        $nombres = $persona?->nombres ?: ($p->nombre ?? '');
        $apellidos = $persona?->apellidos ?? '';
        $ruc = $p->ruc;

        $data = [
            'id'               => $p->id,
            'nombres'          => $nombres,
            'apellidos'        => $apellidos,
            'nombre_completo'  => $p->displayName(),
            'tipo_documento'   => $ruc ? 'RUC' : $persona?->tipo_documento,
            'numero_documento' => $ruc ?: $persona?->numero_documento,
            'telefono'         => $p->telefono ?: $persona?->telefono,
            'email'            => $p->email ?: $persona?->email,
            'direccion'        => $persona?->direccion,
            'empresa'          => $p->empresa,
            'ruc'              => $ruc,
            'activo'           => (bool) ($p->activo ?? $persona?->activo ?? true),
        ];

        if ($conProductos) {
            $data['productos'] = $p->productos;
        }

        return $data;
    }
}
