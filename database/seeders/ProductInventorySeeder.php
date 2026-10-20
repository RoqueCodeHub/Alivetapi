<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class ProductInventorySeeder extends Seeder
{
    public function run(): void
    {
        $path = 'D:\\DESCARGAS\\Inventario_veterinaria.xlsx';
        if (! is_file($path)) {
            $path = storage_path('app/imports/Inventario_veterinaria.xlsx');
        }
        if (! is_file($path)) {
            $this->command?->warn('No se encontró Inventario_veterinaria.xlsx; se omite el stock.');

            return;
        }

        Artisan::call('inventory:import-xlsx', ['path' => $path]);
        $this->command?->info(trim(Artisan::output()));
    }
}
