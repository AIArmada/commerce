<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\CreatePermission;
use AIArmada\FilamentAuthz\Resources\PermissionResource\Pages\EditPermission;
use AIArmada\FilamentAuthz\Resources\UserResource;
use AIArmada\FilamentAuthz\Resources\UserResource\Pages\EditUser;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Mockery;
use ReflectionClass;
use ReflectionMethod;
use Spatie\Permission\PermissionRegistrar;

afterEach(function (): void {
    Mockery::close();
});

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

    it('executes getHeaderActions', function (): void {
        $page = new EditPermission;

        $method = new ReflectionMethod(EditPermission::class, 'getHeaderActions');
        $method->setAccessible(true);

        $actions = $method->invoke($page);

        expect($actions)->toHaveCount(1)
            ->and($actions[0])->toBeInstanceOf(DeleteAction::class);
    });

    it('executes afterSave and clears permission cache', function (): void {
        $registrar = Mockery::mock(PermissionRegistrar::class);
        $registrar->shouldReceive('forgetCachedPermissions')->once();
        app()->instance(PermissionRegistrar::class, $registrar);

        $page = new EditPermission;

        $method = new ReflectionMethod(EditPermission::class, 'afterSave');
        $method->setAccessible(true);
        $method->invoke($page);

        expect(true)->toBeTrue();
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

    it('executes afterCreate and clears permission cache', function (): void {
        $registrar = Mockery::mock(PermissionRegistrar::class);
        $registrar->shouldReceive('forgetCachedPermissions')->once();
        app()->instance(PermissionRegistrar::class, $registrar);

        $page = new CreatePermission;

        $method = new ReflectionMethod(CreatePermission::class, 'afterCreate');
        $method->setAccessible(true);
        $method->invoke($page);

        expect(true)->toBeTrue();
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

    it('executes getHeaderActions', function (): void {
        $page = new EditUser;

        $method = new ReflectionMethod(EditUser::class, 'getHeaderActions');
        $method->setAccessible(true);

        $actions = $method->invoke($page);

        expect($actions)->toHaveCount(1)
            ->and($actions[0])->toBeInstanceOf(DeleteAction::class);
    });
});
