<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create super admin role
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // Create admin role with all permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // Create some default permissions
        $permissions = [
            'role.viewAny',
            'role.view',
            'role.create',
            'role.update',
            'role.delete',
            'permission.viewAny',
            'permission.view',
            'permission.create',
            'permission.update',
            'permission.delete',
            'user.viewAny',
            'user.view',
            'user.create',
            'user.update',
            'user.delete',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // Assign all permissions to admin role
        $admin->syncPermissions(Permission::all());

        // Assign super_admin role to the first user (admin)
        $adminUser = User::where('email', 'admin@commerce.demo')->first();
        if ($adminUser) {
            $adminUser->assignRole($superAdmin);
        }
    }
}
