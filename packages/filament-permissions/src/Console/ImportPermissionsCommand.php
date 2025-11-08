<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ImportPermissionsCommand extends Command
{
    protected $signature = 'permissions:import {path=storage/permissions.json} {--flush-cache}';

    protected $description = 'Import roles & permissions from a JSON file.';

    public function handle(): int
    {
        $path = $this->argument('path');
        $fs = app(Filesystem::class);
        if (! $fs->exists($path)) {
            $this->error('File not found: '.$path);

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
    }
}
