<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            ['nombre' => 'admin', 'descripcion' => 'Acceso total al sistema'],
            ['nombre' => 'veterinario', 'descripcion' => 'Gestión médica y productos'],
            ['nombre' => 'recepcionista', 'descripcion' => 'Gestión de clientes y citas'],
        ]);
    }
}
