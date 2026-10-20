<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'products.view',
            'products.create',
            'products.update',
            'inventory.view',
            'inventory.adjust',
            'inventory.count',
            'purchases.view',
            'purchases.create',
            'sales.view',
            'sales.create',
            'sales.charge',
            'sales.void',
            'cash.open',
            'cash.close',
            'customers.view',
            'customers.create',
            'customers.update',
            'costs.view',
            'reports.view',
            'reports.view_all',
            'clinic.view',
            'clinic.manage',
            'clinic.attend',
            'clinic.charge',
            'reminders.view',
            'audit.view',
            'invoicing.view',
            'invoicing.emit',
            'portal.access',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $roles = [
            'Administrador',
            'Operador de tienda',
            'Cajero',
            'Almacenero',
            'Veterinario',
            'Groomer / peluquero',
            'Recepcionista',
            'Contador',
            'Auditor',
            'Cliente',
            'Soporte técnico',
        ];

        foreach ($roles as $name) {
            Role::findOrCreate($name, 'web');
        }

        Role::findByName('Administrador', 'web')->syncPermissions($permissions);

        Role::findByName('Operador de tienda', 'web')->syncPermissions([
            'products.view',
            'products.create',
            'products.update',
            'inventory.view',
            'inventory.adjust',
            'purchases.view',
            'purchases.create',
            'sales.view',
            'sales.create',
            'reports.view',
            'customers.view',
            'customers.create',
            'customers.update',
            'clinic.view',
            'clinic.charge',
            'reminders.view',
            'invoicing.view',
            'invoicing.emit',
        ]);

        Role::findByName('Cajero', 'web')->syncPermissions([
            'products.view',
            'inventory.view',
            'sales.view',
            'sales.create',
            'sales.charge',
            'cash.open',
            'cash.close',
            'reports.view',
            'customers.view',
            'customers.create',
            'customers.update',
            'clinic.view',
            'clinic.charge',
            'reminders.view',
            'invoicing.view',
            'invoicing.emit',
        ]);

        Role::findByName('Recepcionista', 'web')->syncPermissions([
            'products.view',
            'sales.view',
            'customers.view',
            'customers.create',
            'customers.update',
            'clinic.view',
            'clinic.manage',
            'reminders.view',
        ]);

        Role::findByName('Veterinario', 'web')->syncPermissions([
            'products.view',
            'inventory.view',
            'customers.view',
            'customers.create',
            'customers.update',
            'clinic.view',
            'clinic.manage',
            'clinic.attend',
            'reminders.view',
        ]);

        Role::findByName('Groomer / peluquero', 'web')->syncPermissions([
            'products.view',
            'customers.view',
            'clinic.view',
            'clinic.manage',
            'clinic.attend',
            'reminders.view',
        ]);

        Role::findByName('Contador', 'web')->syncPermissions([
            'reports.view',
            'reports.view_all',
            'costs.view',
            'invoicing.view',
            'invoicing.emit',
            'sales.view',
        ]);

        Role::findByName('Auditor', 'web')->syncPermissions([
            'audit.view',
            'reports.view',
            'reports.view_all',
            'invoicing.view',
        ]);

        Role::findByName('Almacenero', 'web')->syncPermissions([
            'products.view',
            'inventory.view',
            'inventory.adjust',
            'inventory.count',
            'purchases.view',
            'purchases.create',
            'reminders.view',
        ]);

        Role::findByName('Cliente', 'web')->syncPermissions([
            'portal.access',
        ]);

        $assignments = [
            'admin@alivet.com' => 'Administrador',
            'admin2@alivet.com' => 'Administrador',
            'operador1@alivet.com' => 'Operador de tienda',
            'operador2@alivet.com' => 'Operador de tienda',
            'cajera1@alivet.com' => 'Cajero',
            'cajera2@alivet.com' => 'Cajero',
        ];

        foreach ($assignments as $email => $role) {
            $user = User::query()->where('email', $email)->first();
            if ($user) {
                $user->forceFill(['is_active' => true])->save();
                $user->syncRoles([$role]);
            }
        }
    }
}
