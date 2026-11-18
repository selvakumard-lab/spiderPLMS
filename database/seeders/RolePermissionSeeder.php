<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every permission and the five tenant roles from config/plms.php.
 * Roles are global (tenant_id = null) so all tenants share one definition,
 * while role *assignments* are stored per tenant.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(config('plms.permissions'))->flatten();

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (config('plms.roles') as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => null]);
            $role->syncPermissions($permissions === ['*'] ? $all->all() : $permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
