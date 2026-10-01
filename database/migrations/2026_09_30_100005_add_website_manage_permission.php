<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Adds website.manage to existing databases without re-running
// RolePermissionSeeder (its syncPermissions() would wipe any role edits
// made through Admin > Roles). Fresh installs get it from the seeder too.
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'website.manage', 'guard_name' => 'web']);

        foreach (['owner', 'manager'] as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'website.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
