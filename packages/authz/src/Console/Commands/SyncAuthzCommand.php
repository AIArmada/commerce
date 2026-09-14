<?php

declare(strict_types=1);

namespace AIArmada\Authz\Console\Commands;

use AIArmada\Authz\Console\Concerns\Prohibitable;
use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class SyncAuthzCommand extends Command
{
    use Prohibitable;

    protected $signature = 'authz:sync
        {--flush-cache : Flush permission cache after sync}';

    protected $description = 'Sync roles and permissions from config(authz.sync).';

    public function handle(): int
    {
        if (! $this->initializeProhibitable()) {
            return self::FAILURE;
        }

        $config = (array) config('authz.sync');
        $permissions = (array) ($config['permissions'] ?? []);
        $roles = (array) ($config['roles'] ?? []);
        $guards = $this->validateGuards((array) config('authz.guards', ['web']));

        if ($guards === []) {
            $this->error('No valid guards configured.');

            return self::FAILURE;
        }

        $errors = $this->validateSyncConfig($permissions, $roles);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $permissionCount = 0;
        $roleCount = 0;

        DB::transaction(function () use ($permissions, $roles, $guards, &$permissionCount, &$roleCount): void {
            foreach ($permissions as $permission) {
                foreach ($guards as $guard) {
                    Permission::findOrCreate($permission, $guard);
                    $permissionCount++;
                }
            }

            foreach ($roles as $roleName => $perms) {
                foreach ($guards as $guard) {
                    /** @var Role $role */
                    $role = Role::findOrCreate($roleName, $guard);
                    $role->syncPermissions($perms);
                    $roleCount++;
                }
            }
        });

        if ($this->option('flush-cache')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info('Permission cache flushed.');
        }

        $this->info("Synced {$permissionCount} permissions and {$roleCount} roles.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function validateSyncConfig(array $permissions, array $roles): array
    {
        $errors = [];

        foreach ($permissions as $index => $permission) {
            if (! is_string($permission) || $permission === '') {
                $errors[] = "Invalid permission at authz.sync.permissions.{$index}: expected a non-empty string.";
            }
        }

        foreach ($roles as $roleName => $perms) {
            if (! is_string($roleName) || $roleName === '') {
                $errors[] = 'Invalid role name in authz.sync.roles: expected a non-empty string key.';

                continue;
            }

            if (! is_array($perms)) {
                $errors[] = "Invalid permissions for role [{$roleName}]: expected an array of permission names.";

                continue;
            }

            foreach ($perms as $index => $permission) {
                if (! is_string($permission) || $permission === '') {
                    $errors[] = "Invalid permission at authz.sync.roles.{$roleName}.{$index}: expected a non-empty string.";
                }
            }
        }

        return $errors;
    }

    /**
     * @param  list<string>  $guards
     * @return list<string>
     */
    protected function validateGuards(array $guards): array
    {
        $configuredGuards = array_keys((array) config('auth.guards', []));
        $validGuards = [];

        foreach ($guards as $guard) {
            if (in_array($guard, $configuredGuards, true)) {
                $validGuards[] = $guard;
            } else {
                $this->warn("Guard '{$guard}' not configured in auth.guards, skipping.");
            }
        }

        return $validGuards;
    }
}
