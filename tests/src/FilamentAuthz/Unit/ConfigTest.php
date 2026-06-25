<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Models\AuthzScope;
use AIArmada\CommerceSupport\Models\Permission;
use AIArmada\CommerceSupport\Models\Role;

describe('config', function (): void {
    it('has guards configured', function (): void {
        expect(config('authz.guards'))->toBeArray();
        expect(config('authz.guards'))->toContain('web');
    });

    it('has super admin role configured', function (): void {
        expect(config('authz.super_admin_role'))->toBeString();
        expect(config('authz.super_admin_role'))->not->toBeEmpty();
    });

    it('has wildcard permissions setting', function (): void {
        expect(config('authz.wildcard_permissions'))->toBeBool();
    });

    it('has navigation settings', function (): void {
        expect(config('filament-authz.navigation.group'))->toBeString();
        expect(config('filament-authz.navigation.sort'))->toBeInt();
    });

    it('has sync settings', function (): void {
        expect(config('authz.sync'))->toBeArray();
        expect(config('authz.sync.permissions'))->toBeArray();
        expect(config('authz.sync.roles'))->toBeArray();
    });

    it('configures UUID permission models and authz table names', function (): void {
        expect(config('permission.models.permission'))->toBe(Permission::class)
            ->and(config('permission.models.role'))->toBe(Role::class)
            ->and(config('permission.table_names.roles'))->toBe(AIArmada\Authz\authz_table('roles'))
            ->and(config('permission.table_names.permissions'))->toBe(AIArmada\Authz\authz_table('permissions'));
    });

    it('resolves the Authz scope table from core config', function (): void {
        config()->set('authz.database.table_prefix', 'tenant_');
        config()->set('authz.database.tables.scopes', 'scopes');

        expect((new AuthzScope)->getTable())->toBe('tenant_scopes');
    });
});
