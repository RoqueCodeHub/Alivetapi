<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TrabajadorController extends Controller
{
    // Cargo → rol del sistema
    private const CARGO_ROL = [
        'Veterinario'   => 'veterinario',
        'Veterinaria'   => 'veterinario',
        'Recepcionista' => 'recepcionista',
    ];

    // Listar todos los trabajadores
    public function index()
    {
        $trabajadores = Trabajador::with(['persona', 'user:id,email,role_id', 'user.role:id,nombre'])
            ->get()
            ->map(fn($t) => $this->format($t));

        return response()->json([
            'message'      => 'Trabajadores obtenidos exitosamente',
            'total'        => $trabajadores->count(),
            'trabajadores' => $trabajadores,
        ]);
    }

    // Registrar nuevo trabajador
    public function store(Request $request)
    {
        $request->validate([
            'nombres'          => 'required|string|max:255',
            'apellidos'        => 'required|string|max:255',
            'tipo_documento'   => 'required|in:DNI,CE,PASAPORTE',
            'numero_documento' => 'required|string|unique:personas,numero_documento',
            'telefono'         => 'nullable|string|max:50',
            'email'            => 'required|email|unique:personas,email|unique:users,email',
            'direccion'        => 'nullable|string',
            'cargo'            => 'required|string|max:100',
            'especialidad'     => 'nullable|string|max:100',
            'salario'          => 'nullable|numeric|min:0',
            'fecha_contratacion' => 'required|date',
        ]);

        $trabajador = DB::transaction(function () use ($request) {
            $persona = Persona::create($request->only([
                'nombres', 'apellidos', 'tipo_documento', 'numero_documento',
                'telefono', 'email', 'direccion',
            ]));

            // Rol según cargo (default: recepcionista)
            $nombreRol = self::CARGO_ROL[$request->cargo] ?? 'recepcionista';
            $role      = Role::where('nombre', $nombreRol)->first();

            $user = User::create([
                'name'     => $persona->nombre_completo,
                'email'    => $persona->email,
                'password' => Hash::make($persona->numero_documento), // contraseña = DNI
                'role_id'  => $role->id,
            ]);

            return Trabajador::create([
                'persona_id'         => $persona->id,
                'user_id'            => $user->id,
                'cargo'              => $request->cargo,
                'especialidad'       => $request->especialidad,
                'salario'            => $request->salario,
                'fecha_contratacion' => $request->fecha_contratacion,
            ]);
        });

        return response()->json([
            'message'    => 'Trabajador registrado exitosamente',
            'trabajador' => $this->format($trabajador->load(['persona', 'user.role'])),
        ], 201);
    }

    // Ver un trabajador
    public function show($id)
    {
        $trabajador = Trabajador::with(['persona', 'user:id,email,role_id', 'user.role:id,nombre'])->find($id);

        if (!$trabajador) {
            return response()->json(['message' => 'Trabajador no encontrado'], 404);
        }

        return response()->json(['trabajador' => $this->format($trabajador)]);
    }

    // Actualizar trabajador
    public function update(Request $request, $id)
    {
        $trabajador = Trabajador::with(['persona', 'user'])->find($id);

        if (!$trabajador) {
            return response()->json(['message' => 'Trabajador no encontrado'], 404);
        }

        $request->validate([
            'nombres'          => 'sometimes|string|max:255',
            'apellidos'        => 'sometimes|string|max:255',
            'tipo_documento'   => 'sometimes|in:DNI,CE,PASAPORTE',
            'numero_documento' => 'sometimes|string|unique:personas,numero_documento,' . $trabajador->persona_id,
            'telefono'         => 'nullable|string|max:50',
            'email'            => 'sometimes|email|unique:personas,email,' . $trabajador->persona_id . '|unique:users,email,' . $trabajador->user_id,
            'direccion'        => 'nullable|string',
            'activo'           => 'boolean',
            'cargo'            => 'sometimes|string|max:100',
            'especialidad'     => 'nullable|string|max:100',
            'salario'          => 'nullable|numeric|min:0',
            'fecha_contratacion' => 'sometimes|date',
        ]);

        DB::transaction(function () use ($request, $trabajador) {
            $trabajador->persona->update($request->only([
                'nombres', 'apellidos', 'tipo_documento', 'numero_documento',
                'telefono', 'email', 'direccion', 'activo',
            ]));

            $trabajador->update($request->only([
                'cargo', 'especialidad', 'salario', 'fecha_contratacion',
            ]));

            // Sincronizar nombre y email del usuario
            $userUpdate = ['name' => $trabajador->persona->nombre_completo];
            if ($request->filled('email')) {
                $userUpdate['email'] = $request->email;
            }

            // Actualizar rol si cambió el cargo
            if ($request->filled('cargo')) {
                $nombreRol = self::CARGO_ROL[$request->cargo] ?? 'recepcionista';
                $role      = Role::where('nombre', $nombreRol)->first();
                $userUpdate['role_id'] = $role->id;
            }

            $trabajador->user->update($userUpdate);
        });

        return response()->json([
            'message'    => 'Trabajador actualizado exitosamente',
            'trabajador' => $this->format($trabajador->load(['persona', 'user.role'])),
        ]);
    }

    // Eliminar trabajador
    public function destroy($id)
    {
        $trabajador = Trabajador::with(['persona', 'user'])->find($id);

        if (!$trabajador) {
            return response()->json(['message' => 'Trabajador no encontrado'], 404);
        }

        if ($trabajador->ventas()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el trabajador porque tiene ventas registradas. Puede desactivarlo cambiando activo a false.',
            ], 409);
        }

        DB::transaction(function () use ($trabajador) {
            $user    = $trabajador->user;
            $persona = $trabajador->persona;
            $trabajador->delete();
            $user?->delete();
            $persona->delete();
        });

        return response()->json(['message' => 'Trabajador eliminado exitosamente']);
    }

    // Helper: formato de respuesta
    private function format(Trabajador $t): array
    {
        return [
            'id'                 => $t->id,
            'nombres'            => optional($t->persona)->nombres,
            'apellidos'          => optional($t->persona)->apellidos,
            'nombre_completo'    => optional($t->persona)->nombre_completo,
            'tipo_documento'     => optional($t->persona)->tipo_documento,
            'numero_documento'   => optional($t->persona)->numero_documento,
            'telefono'           => optional($t->persona)->telefono,
            'email'              => optional($t->persona)->email,
            'direccion'          => optional($t->persona)->direccion,
            'activo'             => optional($t->persona)->activo,
            'cargo'              => $t->cargo,
            'especialidad'       => $t->especialidad,
            'salario'            => $t->salario,
            'fecha_contratacion' => $t->fecha_contratacion?->format('Y-m-d'),
            'usuario'            => $t->user ? [
                'id'    => $t->user->id,
                'email' => $t->user->email,
                'rol'   => optional($t->user->role)->nombre,
            ] : null,
        ];
    }
}
