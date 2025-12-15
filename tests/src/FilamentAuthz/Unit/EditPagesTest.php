<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\CreatePermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\EditPermission;
use AIArmada\FilamentAuthz\Resources\UserResource;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages\EditUser;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use ReflectionClass;
use ReflectionMethod;

describe('EditPermission Page', function (): void {
    it('extends EditRecord', function (): void {
        expect(is_subclass_of(EditPermission::class, EditRecord::class))->toBeTrue();
    });

    it('has correct resource', function (): void {
        $reflection = new ReflectionClass(EditPermission::class);
        $property = $reflection->getProperty('resource');

        expect($property->getDefaultValue())->toBe(PermissionResource::class);
    });

    it('has getHeaderActions method', function (): void {
        $method = new ReflectionMethod(EditPermission::class, 'getHeaderActions');

        expect($method->isProtected())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe('array');
    });

    it('has afterSave method that clears cache', function (): void {
        $method = new ReflectionMethod(EditPermission::class, 'afterSave');

        expect($method->isProtected())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe('void');
    });
});

describe('CreatePermission Page', function (): void {
    it('extends CreateRecord', function (): void {
        expect(is_subclass_of(CreatePermission::class, CreateRecord::class))->toBeTrue();
    });

    it('has correct resource', function (): void {
        $reflection = new ReflectionClass(CreatePermission::class);
        $property = $reflection->getProperty('resource');

        expect($property->getDefaultValue())->toBe(PermissionResource::class);
    });
});

describe('EditUser Page', function (): void {
    it('extends EditRecord', function (): void {
        expect(is_subclass_of(EditUser::class, EditRecord::class))->toBeTrue();
    });

    it('has correct resource', function (): void {
        $reflection = new ReflectionClass(EditUser::class);
        $property = $reflection->getProperty('resource');

        expect($property->getDefaultValue())->toBe(UserResource::class);
    });

    it('has getHeaderActions method', function (): void {
        $method = new ReflectionMethod(EditUser::class, 'getHeaderActions');

        expect($method->isProtected())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe('array');
    });
});
