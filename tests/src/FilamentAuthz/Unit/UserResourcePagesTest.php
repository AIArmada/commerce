<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Resources\UserResource;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages\CreateUser;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages\EditUser;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages\ListUsers;
use ReflectionMethod;
use ReflectionProperty;

describe('UserResource Pages', function (): void {
    describe('ListUsers', function (): void {
        it('uses correct resource', function (): void {
            $reflection = new ReflectionProperty(ListUsers::class, 'resource');
            $reflection->setAccessible(true);

            expect($reflection->getValue())->toBe(UserResource::class);
        });

        it('has header actions including create', function (): void {
            $page = new ListUsers();

            $method = new ReflectionMethod(ListUsers::class, 'getHeaderActions');
            $method->setAccessible(true);

            $actions = $method->invoke($page);

            expect($actions)->toBeArray()
                ->and(count($actions))->toBeGreaterThan(0);
        });
    });

    describe('CreateUser', function (): void {
        it('uses correct resource', function (): void {
            $reflection = new ReflectionProperty(CreateUser::class, 'resource');
            $reflection->setAccessible(true);

            expect($reflection->getValue())->toBe(UserResource::class);
        });
    });

    describe('EditUser', function (): void {
        it('uses correct resource', function (): void {
            $reflection = new ReflectionProperty(EditUser::class, 'resource');
            $reflection->setAccessible(true);

            expect($reflection->getValue())->toBe(UserResource::class);
        });
    });
});
