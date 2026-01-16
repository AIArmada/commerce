<?php

declare(strict_types=1);

describe('config', function (): void {
    it('has guards configured', function (): void {
        expect(config('filament-authz.guards'))->toBeArray();
        expect(config('filament-authz.guards'))->toContain('web');
    });

    it('has super admin role configured', function (): void {
        expect(config('filament-authz.super_admin_role'))->toBeString();
        expect(config('filament-authz.super_admin_role'))->not->toBeEmpty();
    });

    it('has wildcard permissions setting', function (): void {
        expect(config('filament-authz.wildcard_permissions'))->toBeBool();
    });

    it('has navigation settings', function (): void {
        expect(config('filament-authz.navigation.group'))->toBeString();
        expect(config('filament-authz.navigation.sort'))->toBeInt();
    });

    it('has sync settings', function (): void {
        expect(config('filament-authz.sync'))->toBeArray();
        expect(config('filament-authz.sync.permissions'))->toBeArray();
        expect(config('filament-authz.sync.roles'))->toBeArray();
    });
});
