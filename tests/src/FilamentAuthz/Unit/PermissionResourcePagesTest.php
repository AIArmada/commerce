<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\CreatePermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\EditPermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\ListPermissions;

describe('PermissionResource Pages', function (): void {
    describe('ListPermissions', function (): void {
        it('uses correct resource', function (): void {
            $reflection = new \ReflectionProperty(ListPermissions::class, 'resource');
            $reflection->setAccessible(true);

            expect($reflection->getValue())->toBe(PermissionResource::class);
        });

        it('has header actions including create', function (): void {
            $page = new ListPermissions();

            $method = new \ReflectionMethod(ListPermissions::class, 'getHeaderActions');
            $method->setAccessible(true);

            $actions = $method->invoke($page);

            expect($actions)->toBeArray()
                ->and(count($actions))->toBeGreaterThan(0);
        });
    });

    describe('CreatePermission', function (): void {
        it('uses correct resource', function (): void {
            $reflection = new \ReflectionProperty(CreatePermission::class, 'resource');
            $reflection->setAccessible(true);

            expect($reflection->getValue())->toBe(PermissionResource::class);
        });
    });

    describe('EditPermission', function (): void {
        it('uses correct resource', function (): void {
            $reflection = new \ReflectionProperty(EditPermission::class, 'resource');
            $reflection->setAccessible(true);

            expect($reflection->getValue())->toBe(PermissionResource::class);
        });
    });
});
