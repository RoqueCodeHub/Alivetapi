<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetodoPagoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('metodos_pago')) {
            return;
        }

        $now = now();
        $rows = [
            ['codigo' => 'efectivo', 'nombre' => 'Efectivo'],
            ['codigo' => 'yape', 'nombre' => 'Yape'],
            ['codigo' => 'plin', 'nombre' => 'Plin'],
            ['codigo' => 'tarjeta', 'nombre' => 'Tarjeta'],
            ['codigo' => 'transferencia', 'nombre' => 'Transferencia'],
        ];

        DB::table('metodos_pago')->delete();
        foreach ($rows as $index => $row) {
            DB::table('metodos_pago')->insert([
                'id' => $index + 1,
                'codigo' => $row['codigo'],
                'nombre' => $row['nombre'],
                'activo' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::statement('ALTER TABLE metodos_pago AUTO_INCREMENT = 6');
    }
}
