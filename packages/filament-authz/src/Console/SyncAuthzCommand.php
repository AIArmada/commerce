<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

class SyncAuthzCommand extends Command
{
    protected $signature = 'authz:sync
        {--flush-cache : Flush permission cache after sync}
        {--owner-type= : Owner model class or morph type}
        {--owner-id= : Owner model id}';

    protected $description = 'Sync roles & permissions from config(filament-authz.sync).';

    public function handle(): int
    {
        return $this->withOwnerContext(function (): int {
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
        });
    }

    private function withOwnerContext(callable $callback): int
    {
        if (! config('filament-authz.owner.enabled', false)) {
            return (int) $callback();
        }

        if (OwnerContext::resolve() !== null) {
            return (int) $callback();
        }

        $ownerType = $this->option('owner-type');
        $ownerId = $this->option('owner-id');

        if ($ownerType === null || $ownerId === null || $ownerType === '' || $ownerId === '') {
            $this->error('Owner context is required when filament-authz.owner.enabled is true.');
            $this->line('Provide --owner-type and --owner-id, or bind OwnerResolverInterface.');

            return self::FAILURE;
        }

        try {
            $owner = OwnerContext::fromTypeAndId((string) $ownerType, $ownerId);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return (int) OwnerContext::withOwner($owner, $callback);
    }
}
