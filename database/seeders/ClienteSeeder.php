<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Persona;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            [
                'nombres' => 'Ana',
                'apellidos' => 'Torres',
                'tipo_documento' => 'DNI',
                'numero_documento' => '44556677',
                'telefono' => '987654321',
                'email' => 'ana.torres@gmail.com',
                'direccion' => 'Urb. Las Flores 234',
                'observaciones' => null,
            ],
            [
                'nombres' => 'Luis',
                'apellidos' => 'Mendoza',
                'tipo_documento' => 'DNI',
                'numero_documento' => '33445566',
                'telefono' => '976543210',
                'email' => 'luis.mendoza@hotmail.com',
                'direccion' => 'Jr. Huancayo 567',
                'observaciones' => 'Cliente frecuente',
            ],
            [
                'nombres' => 'Rosa',
                'apellidos' => 'Quispe',
                'tipo_documento' => 'DNI',
                'numero_documento' => '55667788',
                'telefono' => '965432109',
                'email' => 'rosa.quispe@gmail.com',
                'direccion' => 'Av. La Marina 890',
                'observaciones' => null,
            ],
            [
                'nombres' => 'Pedro',
                'apellidos' => 'Huamán',
                'tipo_documento' => 'DNI',
                'numero_documento' => '40112233',
                'telefono' => '954321098',
                'email' => 'pedro.huaman@gmail.com',
                'direccion' => 'Av. El Sol 120',
                'observaciones' => null,
            ],
            [
                'nombres' => 'María',
                'apellidos' => 'Cáceres',
                'tipo_documento' => 'DNI',
                'numero_documento' => '41223344',
                'telefono' => '943210987',
                'email' => 'maria.caceres@gmail.com',
                'direccion' => 'Jr. Lima 450',
                'observaciones' => null,
            ],
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Cliente::query()->delete();
        Persona::query()->whereIn('numero_documento', collect($rows)->pluck('numero_documento'))->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $id = 1;
        foreach ($rows as $row) {
            $persona = Persona::create([
                'nombres' => $row['nombres'],
                'apellidos' => $row['apellidos'],
                'tipo_documento' => $row['tipo_documento'],
                'numero_documento' => $row['numero_documento'],
                'telefono' => $row['telefono'],
                'email' => $row['email'],
                'direccion' => $row['direccion'],
                'activo' => true,
            ]);
            DB::table('clientes')->insert([
                'id' => $id++,
                'persona_id' => $persona->id,
                'observaciones' => $row['observaciones'],
                'activo' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::statement('ALTER TABLE clientes AUTO_INCREMENT = 6');
    }
}
