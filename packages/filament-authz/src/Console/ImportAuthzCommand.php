<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

class ImportAuthzCommand extends Command
{
    protected $signature = 'authz:import
        {path=storage/permissions.json}
        {--flush-cache}
        {--owner-type= : Owner model class or morph type}
        {--owner-id= : Owner model id}';

    protected $description = 'Import roles & permissions from a JSON file.';

    public function handle(): int
    {
        return $this->withOwnerContext(function (): int {
            $path = $this->argument('path');
            $fs = app(Filesystem::class);
            if (! $fs->exists($path)) {
                $this->error('File not found: ' . $path);

                return self::FAILURE;
            }

            $payload = json_decode((string) $fs->get($path), true);
            if (! is_array($payload)) {
                $this->error('Invalid JSON payload.');

                return self::FAILURE;
            }

            $permissions = (array) ($payload['permissions'] ?? []);
            $roles = (array) ($payload['roles'] ?? []);

            foreach ($permissions as $perm) {
                if (! isset($perm['name'], $perm['guard_name'])) {
                    continue;
                }
                Permission::findOrCreate($perm['name'], $perm['guard_name']);
            }

            foreach ($roles as $roleData) {
                if (! isset($roleData['name'], $roleData['guard_name'])) {
                    continue;
                }
                $role = Role::findOrCreate($roleData['name'], $roleData['guard_name']);
                $role->syncPermissions($roleData['permissions'] ?? []);
            }

            if ($this->option('flush-cache')) {
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }

            $this->info('Import completed.');

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
