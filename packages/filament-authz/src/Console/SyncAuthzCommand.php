<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncAuthzCommand extends Command
{
    protected $signature = 'authz:sync {--flush-cache : Flush permission cache after sync}';

    protected $description = 'Sync roles & permissions from config(filament-authz.sync).';

    public function handle(): int
    {
        $config = (array) config('filament-authz.sync');
        $permissions = (array) ($config['permissions'] ?? []);
        $roles = (array) ($config['roles'] ?? []);
        $guards = (array) config('filament-authz.guards');

        foreach ($permissions as $permission) {
            foreach ($guards as $guard) {
                Permission::findOrCreate($permission, $guard);
            }
        }

        foreach ($roles as $roleName => $perms) {
            foreach ($guards as $guard) {
                $role = Role::findOrCreate($roleName, $guard);
                $role->syncPermissions($perms);
            }
        }

        if ($this->option('flush-cache')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->info('Permissions & roles synced.');

        return self::SUCCESS;
    }
}
