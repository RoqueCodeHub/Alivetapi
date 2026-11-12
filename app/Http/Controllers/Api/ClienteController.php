<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        $request->validate(
            $this->reglas($request),
            $this->mensajes()
        );

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

        $request->validate(
            $this->reglas($request, $cliente),
            $this->mensajes()
        );

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

    private function reglas(Request $request, ?Cliente $cliente = null): array
    {
        foreach (['numero_documento', 'telefono', 'email', 'direccion', 'observaciones'] as $campo) {
            if ($request->exists($campo) && trim((string) $request->input($campo)) === '') {
                $request->merge([$campo => null]);
            }
        }

        $tipo = strtoupper(trim((string) $request->input('tipo_documento', $cliente?->persona?->tipo_documento ?? 'DNI')));
        if ($tipo === 'PASSPORT') {
            $tipo = 'PASAPORTE';
        }
        if ($request->exists('tipo_documento')) {
            $request->merge(['tipo_documento' => $tipo]);
        }

        $personaId = $cliente?->persona_id;
        $documento = ['nullable', 'string', 'max:12', $this->formatoDocumento($tipo)];
        $documento[] = $personaId
            ? Rule::unique('personas', 'numero_documento')->ignore($personaId)
            : Rule::unique('personas', 'numero_documento');
        $email = ['nullable', 'email', 'max:255'];
        $email[] = $personaId
            ? Rule::unique('personas', 'email')->ignore($personaId)
            : Rule::unique('personas', 'email');

        $reglas = [
            'nombres' => ['string', 'max:255'],
            'apellidos' => $cliente ? ['sometimes', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'tipo_documento' => ['in:DNI,RUC,CE,PASAPORTE'],
            'numero_documento' => $documento,
            'telefono' => ['nullable', 'regex:/^9\d{8}$/'],
            'email' => $email,
            'direccion' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];

        if ($cliente) {
            $reglas['nombres'][] = 'sometimes';
            $reglas['tipo_documento'][] = 'sometimes';
            $reglas['activo'] = ['boolean'];
        } else {
            $reglas['nombres'][] = 'required';
            $reglas['tipo_documento'][] = 'required';
        }

        return $reglas;
    }

    private function formatoDocumento(string $tipo): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($tipo): void {
            if ($value === null || $value === '') {
                return;
            }
            $numero = (string) $value;
            $valido = match ($tipo) {
                'DNI' => preg_match('/^\d{8}$/', $numero) === 1,
                'RUC' => preg_match('/^\d{11}$/', $numero) === 1,
                'CE' => preg_match('/^[A-Za-z0-9]{9,12}$/', $numero) === 1,
                default => preg_match('/^[A-Za-z0-9]{6,12}$/', $numero) === 1,
            };
            if (! $valido) {
                $fail('El número de documento no corresponde al tipo seleccionado.');
            }
        };
    }

    private function mensajes(): array
    {
        return [
            'nombres.required' => 'Los nombres del cliente son obligatorios.',
            'apellidos.required' => 'Los apellidos del cliente son obligatorios.',
            'email.email' => 'Ingresa un correo válido.',
            'email.unique' => 'Ese correo ya está registrado.',
            'telefono.regex' => 'El teléfono debe tener 9 dígitos y empezar con 9.',
            'numero_documento.unique' => 'Ese documento ya está registrado.',
            'tipo_documento.in' => 'El tipo de documento no es válido.',
        ];
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
