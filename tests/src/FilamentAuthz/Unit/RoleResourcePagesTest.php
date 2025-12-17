<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\CreateRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\EditRole;
use AIArmada\FilamentAuthz\Resources\RoleResource\Pages\ListRoles;
use ReflectionMethod;
use ReflectionProperty;

describe('RoleResource Pages', function (): void {
    describe('ListRoles', function (): void {
        it('uses correct resource', function (): void {
            $reflection = new ReflectionProperty(ListRoles::class, 'resource');
            $reflection->setAccessible(true);

            expect($reflection->getValue())->toBe(RoleResource::class);
        });

        it('has header actions including create', function (): void {
            $page = new ListRoles;

            $method = new ReflectionMethod(ListRoles::class, 'getHeaderActions');
            $method->setAccessible(true);

            $actions = $method->invoke($page);

            expect($actions)->toBeArray()
                ->and(count($actions))->toBeGreaterThan(0);
        });
    });

    describe('CreateRole', function (): void {
        it('uses correct resource', function (): void {
            $reflection = new ReflectionProperty(CreateRole::class, 'resource');
            $reflection->setAccessible(true);

            expect($reflection->getValue())->toBe(RoleResource::class);
        });

        it('has permissionIds property', function (): void {
            $reflection = new ReflectionProperty(CreateRole::class, 'permissionIds');
            $reflection->setAccessible(true);

            $page = new CreateRole;
            expect($reflection->getValue($page))->toBeArray();
        });

        it('mutates form data before create to extract permissions', function (): void {
            $page = new CreateRole;

            $method = new ReflectionMethod(CreateRole::class, 'mutateFormDataBeforeCreate');
            $method->setAccessible(true);

            $data = [
                'name' => 'test-role',
                'guard_name' => 'web',
                'permissions' => [1, 2, 3],
            ];

            $result = $method->invoke($page, $data);

            expect($result)->not->toHaveKey('permissions')
                ->and($result)->toHaveKey('name')
                ->and($result['name'])->toBe('test-role');

            // Check permissionIds was set
            $permissionIds = new ReflectionProperty(CreateRole::class, 'permissionIds');
            $permissionIds->setAccessible(true);
            expect($permissionIds->getValue($page))->toEqual(['1', '2', '3']);
        });
    });

    describe('EditRole', function (): void {
        it('uses correct resource', function (): void {
            $reflection = new ReflectionProperty(EditRole::class, 'resource');
            $reflection->setAccessible(true);

            expect($reflection->getValue())->toBe(RoleResource::class);
        });
    });
});
