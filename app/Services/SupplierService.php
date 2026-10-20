<?php

namespace App\Services;

use App\Models\Persona;
use App\Models\Proveedor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function all(): Collection
    {
        return Proveedor::query()->with('persona')->orderBy('id')->get();
    }

    public function create(array $data): Proveedor
    {
        return DB::transaction(function () use ($data) {
            $name = $data['name'];
            $email = $data['email'] ?? null;

            $persona = Persona::create([
                'nombres' => $name,
                'apellidos' => '.',
                'tipo_documento' => 'DNI',
                'numero_documento' => null,
                'telefono' => $data['phone'] ?? null,
                'email' => $this->personaEmail($email),
                'activo' => $data['activo'] ?? true,
            ]);

            return Proveedor::create([
                'persona_id' => $persona->id,
                'empresa' => $name,
                'nombre' => $name,
                'ruc' => $data['ruc'] ?? null,
                'telefono' => $data['phone'] ?? null,
                'email' => $email,
                'activo' => $data['activo'] ?? true,
            ])->load('persona');
        });
    }

    public function update(Proveedor $proveedor, array $data): Proveedor
    {
        return DB::transaction(function () use ($proveedor, $data) {
            $name = $data['name'] ?? $proveedor->displayName();
            $proveedor->fill([
                'empresa' => $name,
                'nombre' => $name,
                'ruc' => array_key_exists('ruc', $data) ? $data['ruc'] : $proveedor->ruc,
                'telefono' => array_key_exists('phone', $data) ? $data['phone'] : $proveedor->telefono,
                'email' => array_key_exists('email', $data) ? $data['email'] : $proveedor->email,
                'activo' => $data['activo'] ?? $proveedor->activo,
            ])->save();

            if ($proveedor->persona) {
                $proveedor->persona->fill([
                    'nombres' => $name,
                    'telefono' => $proveedor->telefono,
                ])->save();
            }

            return $proveedor->fresh('persona');
        });
    }

    private function personaEmail(?string $email): ?string
    {
        if (! $email) {
            return null;
        }

        $taken = Persona::query()->where('email', $email)->exists();

        return $taken ? null : $email;
    }
}
