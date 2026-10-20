<?php

namespace Database\Seeders;

use App\Models\Proveedor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProveedorSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [
            [
                'id' => 1,
                'persona_id' => null,
                'empresa' => 'CLINICA VETERINARIA DR. BORIS E.I.R.L.',
                'nombre' => 'CLINICA VETERINARIA DR. BORIS',
                'ruc' => '20542827883',
                'telefono' => null,
                'email' => null,
            ],
            [
                'id' => 2,
                'persona_id' => null,
                'empresa' => 'CLINICA VETERINARIA MALDONADO E.I.R.L.',
                'nombre' => 'VETERINARIA MALDONADO',
                'ruc' => '20612669946',
                'telefono' => '914816888',
                'email' => null,
            ],
            [
                'id' => 3,
                'persona_id' => null,
                'empresa' => 'CLINICA VETERINARIA RAZAS DOS DE MAYO E.I.R.L.',
                'nombre' => 'VETERINARIA RAZAS',
                'ruc' => '20609873346',
                'telefono' => '973166172',
                'email' => null,
            ],
            [
                'id' => 4,
                'persona_id' => null,
                'empresa' => 'MAKAFE VET E.I.R.L.',
                'nombre' => 'MAKAFE VET',
                'ruc' => '20604486247',
                'telefono' => null,
                'email' => null,
            ],
            [
                'id' => 5,
                'persona_id' => null,
                'empresa' => 'VETERINARIA ANIMAL VET E.I.R.L.',
                'nombre' => 'VETERINARIA ANIMAL VET',
                'ruc' => '20611863731',
                'telefono' => null,
                'email' => null,
            ],
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('productos')->whereNotNull('proveedor_id')->update(['proveedor_id' => null]);
        if (DB::getSchemaBuilder()->hasTable('purchases')) {
            DB::table('purchases')->update(['supplier_id' => 1]);
        }
        Proveedor::query()->delete();

        foreach ($rows as $row) {
            DB::table('proveedores')->insert($row + [
                'activo' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::statement('ALTER TABLE proveedores AUTO_INCREMENT = 6');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
