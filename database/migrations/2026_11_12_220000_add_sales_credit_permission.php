<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('sales.credit', 'web');

        $admin = Role::query()->where('name', 'Administrador')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo('sales.credit');
        }
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::query()->where('name', 'sales.credit')->where('guard_name', 'web')->first();
        if ($permission) {
            $permission->delete();
        }
    }
};
