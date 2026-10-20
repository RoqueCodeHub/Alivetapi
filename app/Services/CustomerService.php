<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Persona;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function search(?string $q = null)
    {
        return Cliente::query()
            ->with('persona')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->whereHas('persona', function ($p) use ($q) {
                            $p->where('nombres', 'like', '%'.$q.'%')
                                ->orWhere('apellidos', 'like', '%'.$q.'%')
                                ->orWhere('numero_documento', 'like', '%'.$q.'%')
                                ->orWhere('telefono', 'like', '%'.$q.'%')
                                ->orWhere('email', 'like', '%'.$q.'%');
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function create(array $data): Cliente
    {
        return DB::transaction(function () use ($data) {
            [$nombres, $apellidos] = $this->splitName($data['name']);
            $tipo = $data['document_type'] ?? 'DNI';

            $persona = Persona::create([
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'tipo_documento' => in_array($tipo, ['DNI', 'CE', 'PASAPORTE', 'RUC'], true) ? $tipo : 'DNI',
                'numero_documento' => $data['document_number'] ?? null,
                'telefono' => $data['phone'] ?? $data['whatsapp'] ?? null,
                'activo' => true,
            ]);

            return Cliente::create([
                'persona_id' => $persona->id,
                'observaciones' => $data['notes'] ?? null,
                'activo' => $data['activo'] ?? true,
            ])->load('persona');
        });
    }

    public function update(Cliente $cliente, array $data): Cliente
    {
        return DB::transaction(function () use ($cliente, $data) {
            if (isset($data['name'])) {
                [$nombres, $apellidos] = $this->splitName($data['name']);
                $cliente->persona?->fill([
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                ]);
            }
            if (array_key_exists('notes', $data)) {
                $cliente->observaciones = $data['notes'];
            }
            if (array_key_exists('activo', $data)) {
                $cliente->activo = $data['activo'];
            }
            $cliente->save();

            if ($cliente->persona) {
                if (isset($data['document_type'])) {
                    $cliente->persona->tipo_documento = $data['document_type'];
                }
                if (array_key_exists('document_number', $data)) {
                    $cliente->persona->numero_documento = $data['document_number'];
                }
                if (array_key_exists('phone', $data) || array_key_exists('whatsapp', $data)) {
                    $cliente->persona->telefono = $data['phone'] ?? $data['whatsapp'];
                }
                $cliente->persona->save();
            }

            return $cliente->fresh(['persona', 'pets']);
        });
    }

    public function sales(Cliente $cliente)
    {
        return Sale::query()
            ->with(['items', 'payments', 'user', 'pet'])
            ->where('customer_id', $cliente->id)
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $name = trim($name);
        $parts = preg_split('/\s+/', $name) ?: [$name];
        $nombres = array_shift($parts) ?: $name;
        $apellidos = $parts === [] ? '.' : implode(' ', $parts);

        return [$nombres, $apellidos];
    }
}
