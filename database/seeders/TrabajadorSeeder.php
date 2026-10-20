<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Persona;
use App\Models\Trabajador;
use App\Models\User;
use App\Models\Role;

class TrabajadorSeeder extends Seeder
{
    // Mapeo cargo → nombre de rol
    private const CARGO_ROL = [
        'Veterinario'  => 'veterinario',
        'Veterinaria'  => 'veterinario',
        'Recepcionista' => 'recepcionista',
    ];

    public function run(): void
    {
        $roles = Role::pluck('id', 'nombre'); // ['admin' => 1, 'veterinario' => 2, ...]

        $trabajadores = [
            [
                'persona' => [
                    'nombres'          => 'Sofía',
                    'apellidos'        => 'Vargas',
                    'tipo_documento'   => 'DNI',
                    'numero_documento' => '22334455',
                    'telefono'         => '954321098',
                    'email'            => 'sofia.vargas@veterinaria.com',
                    'direccion'        => 'Av. Universitaria 321',
                ],
                'cargo'              => 'Veterinaria',
                'especialidad'       => 'Medicina general',
                'salario'            => 3500.00,
                'fecha_contratacion' => '2023-01-15',
            ],
            [
                'persona' => [
                    'nombres'          => 'Diego',
                    'apellidos'        => 'Flores',
                    'tipo_documento'   => 'DNI',
                    'numero_documento' => '66778899',
                    'telefono'         => '943210987',
                    'email'            => 'diego.flores@veterinaria.com',
                    'direccion'        => 'Jr. Moquegua 654',
                ],
                'cargo'              => 'Veterinario',
                'especialidad'       => 'Cirugía',
                'salario'            => 4200.00,
                'fecha_contratacion' => '2022-06-01',
            ],
            [
                'persona' => [
                    'nombres'          => 'Lucía',
                    'apellidos'        => 'Salinas',
                    'tipo_documento'   => 'DNI',
                    'numero_documento' => '77889900',
                    'telefono'         => '932109876',
                    'email'            => 'lucia.salinas@veterinaria.com',
                    'direccion'        => 'Urb. El Pinar 987',
                ],
                'cargo'              => 'Recepcionista',
                'especialidad'       => null,
                'salario'            => 1800.00,
                'fecha_contratacion' => '2024-03-10',
            ],
        ];

        foreach ($trabajadores as $data) {
            $persona = Persona::create($data['persona']);

            // Determinar rol según cargo (default: recepcionista)
            $nombreRol = self::CARGO_ROL[$data['cargo']] ?? 'recepcionista';
            $roleId    = $roles[$nombreRol] ?? $roles['recepcionista'];

            // Crear usuario: email = correo, password = DNI
            $user = User::create([
                'name'     => "{$persona->nombres} {$persona->apellidos}",
                'email'    => $persona->email,
                'password' => Hash::make($persona->numero_documento),
                'role_id'  => $roleId,
            ]);

            Trabajador::create([
                'persona_id'         => $persona->id,
                'user_id'            => $user->id,
                'cargo'              => $data['cargo'],
                'especialidad'       => $data['especialidad'],
                'salario'            => $data['salario'],
                'fecha_contratacion' => $data['fecha_contratacion'],
            ]);
        }
    }
}
