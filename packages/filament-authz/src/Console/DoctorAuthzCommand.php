<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use InvalidArgumentException;

class DoctorAuthzCommand extends Command
{
    protected $signature = 'authz:doctor
        {--owner-type= : Owner model class or morph type}
        {--owner-id= : Owner model id}';

    protected $description = 'Diagnose permission & role configuration anomalies.';

    public function handle(): int
    {
        return $this->withOwnerContext(function (): int {
            $guards = (array) config('filament-authz.guards');
            $issues = 0;

            // Guard mismatches: roles/permissions whose guard_name not listed.
            $badRoles = Role::query()->whereNotIn('guard_name', $guards)->get();
            $badPerms = Permission::query()->whereNotIn('guard_name', $guards)->get();

            if ($badRoles->isNotEmpty()) {
                $issues += $badRoles->count();
                $this->warn('Roles with invalid guard: ' . $badRoles->pluck('name')->join(', '));
            }
            if ($badPerms->isNotEmpty()) {
                $issues += $badPerms->count();
                $this->warn('Permissions with invalid guard: ' . $badPerms->pluck('name')->join(', '));
            }

            // Unused authz: never attached to any role.
            $unused = Permission::query()->whereDoesntHave('roles')->get();
            if ($unused->isNotEmpty()) {
                $issues += $unused->count();
                $this->line('Unused authz: ' . $unused->pluck('name')->join(', '));
            }

            // Empty roles.
            $emptyRoles = Role::query()->whereDoesntHave('permissions')->get();
            if ($emptyRoles->isNotEmpty()) {
                $issues += $emptyRoles->count();
                $this->line('Roles without authz: ' . $emptyRoles->pluck('name')->join(', '));
            }

            if ($issues === 0) {
                $this->info('No issues detected.');
            } else {
                $this->warn('Total issues: ' . $issues);
            }

            return $issues === 0 ? self::SUCCESS : self::FAILURE;
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
