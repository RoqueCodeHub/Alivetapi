<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            PermissionSeeder::class,
            CatalogSeeder::class,
            ProveedorSeeder::class,
            ClienteSeeder::class,
            MetodoPagoSeeder::class,
            ProductInventorySeeder::class,
        ]);
    }
}
