<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Console;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DoctorAuthzCommand extends Command
{
    protected $signature = 'authz:doctor';

    protected $description = 'Diagnose permission & role configuration anomalies.';

    public function handle(): int
    {
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
    }
}
