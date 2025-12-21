<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use InvalidArgumentException;

class ExportAuthzCommand extends Command
{
    protected $signature = 'authz:export {path=storage/permissions.json}
        {--owner-type= : Owner model class or morph type}
        {--owner-id= : Owner model id}';

    protected $description = 'Export roles & permissions to a JSON file.';

    public function handle(): int
    {
        return $this->withOwnerContext(function (): int {
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
            $this->info('Exported to: ' . $path);

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
