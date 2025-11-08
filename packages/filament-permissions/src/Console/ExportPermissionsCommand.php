<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ExportPermissionsCommand extends Command
{
    protected $signature = 'permissions:export {path=storage/permissions.json}';

    protected $description = 'Export roles & permissions to a JSON file.';

    public function handle(): int
    {
        $fs = app(Filesystem::class);
        $path = $this->argument('path');

        $data = [
            'permissions' => Permission::query()->orderBy('name')->get(['name', 'guard_name'])->toArray(),
            'roles' => Role::query()->orderBy('name')->get(['name', 'guard_name'])->map(function ($role) {
                return [
                    'name' => $role['name'],
                    'guard_name' => $role['guard_name'],
                    'permissions' => Role::where('name', $role['name'])->where('guard_name', $role['guard_name'])->first()?->permissions()->pluck('name')->values()->all() ?? [],
                ];
            })->values()->all(),
        ];

        $fs->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('Exported to: '.$path);

        return self::SUCCESS;
    }
}
