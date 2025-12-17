<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Resources\PermissionResource\RelationManagers\RolesRelationManager as PermissionRolesRelationManager;
use AIArmada\FilamentAuthz\Resources\RoleResource\RelationManagers\PermissionsRelationManager;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\PermissionsRelationManager as UserPermissionsRelationManager;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\RolesRelationManager;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Mockery;
use ReflectionClass;
use ReflectionMethod;

afterEach(function (): void {
    Mockery::close();
});

it('executes RoleResource PermissionsRelationManager table and form', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('headerActions')->once()->andReturnSelf();
    $table->shouldReceive('recordActions')->once()->andReturnSelf();
    $table->shouldReceive('toolbarActions')->once()->andReturnSelf();

    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    $manager = new PermissionsRelationManager();

    expect($manager->table($table))->toBe($table)
        ->and($manager->form($schema))->toBe($schema);
});

it('executes PermissionResource RolesRelationManager table and form', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('headerActions')->once()->andReturnSelf();
    $table->shouldReceive('recordActions')->once()->andReturnSelf();
    $table->shouldReceive('toolbarActions')->once()->andReturnSelf();

    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    $manager = new PermissionRolesRelationManager();

    expect($manager->table($table))->toBe($table)
        ->and($manager->form($schema))->toBe($schema);
});

it('executes UserResource RolesRelationManager table and form', function (): void {
    config()->set('filament-authz.guards', ['web', 'admin']);

    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('headerActions')->once()->andReturnSelf();
    $table->shouldReceive('recordActions')->once()->andReturnSelf();
    $table->shouldReceive('toolbarActions')->once()->andReturnSelf();

    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    $manager = new RolesRelationManager();

    expect($manager->table($table))->toBe($table)
        ->and($manager->form($schema))->toBe($schema);
});

it('executes UserResource PermissionsRelationManager table and form', function (): void {
    config()->set('filament-authz.default_guard_name', 'web');

    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('headerActions')->once()->andReturnSelf();
    $table->shouldReceive('recordActions')->once()->andReturnSelf();
    $table->shouldReceive('toolbarActions')->once()->andReturnSelf();

    $schema = Mockery::mock(Schema::class);
    $schema->shouldReceive('schema')->once()->andReturnSelf();

    $manager = new UserPermissionsRelationManager();

    expect($manager->table($table))->toBe($table)
        ->and($manager->form($schema))->toBe($schema);
});

describe('PermissionsRelationManager (RoleResource)', function (): void {
    it('has correct relationship name', function (): void {
        $reflection = new ReflectionClass(PermissionsRelationManager::class);
        $property = $reflection->getProperty('relationship');

        expect($property->getDefaultValue())->toBe('permissions');
    });

    it('has correct title', function (): void {
        $reflection = new ReflectionClass(PermissionsRelationManager::class);
        $property = $reflection->getProperty('title');

        expect($property->getDefaultValue())->toBe('Permissions');
    });

    it('extends RelationManager', function (): void {
        expect(is_subclass_of(PermissionsRelationManager::class, RelationManager::class))->toBeTrue();
    });

    it('has table method that configures columns', function (): void {
        $method = new ReflectionMethod(PermissionsRelationManager::class, 'table');

        expect($method->isPublic())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe(Table::class);
    });

    it('has form method that configures schema', function (): void {
        $method = new ReflectionMethod(PermissionsRelationManager::class, 'form');

        expect($method->isPublic())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe(Schema::class);
    });
});

describe('RolesRelationManager (UserResource)', function (): void {
    it('has correct relationship name', function (): void {
        $reflection = new ReflectionClass(RolesRelationManager::class);
        $property = $reflection->getProperty('relationship');

        expect($property->getDefaultValue())->toBe('roles');
    });

    it('has correct title', function (): void {
        $reflection = new ReflectionClass(RolesRelationManager::class);
        $property = $reflection->getProperty('title');

        expect($property->getDefaultValue())->toBe('Roles');
    });

    it('extends RelationManager', function (): void {
        expect(is_subclass_of(RolesRelationManager::class, RelationManager::class))->toBeTrue();
    });

    it('has table method that configures columns', function (): void {
        $method = new ReflectionMethod(RolesRelationManager::class, 'table');

        expect($method->isPublic())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe(Table::class);
    });

    it('has form method that configures schema', function (): void {
        $method = new ReflectionMethod(RolesRelationManager::class, 'form');

        expect($method->isPublic())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe(Schema::class);
    });
});

describe('PermissionsRelationManager (UserResource)', function (): void {
    it('has correct relationship name', function (): void {
        $reflection = new ReflectionClass(UserPermissionsRelationManager::class);
        $property = $reflection->getProperty('relationship');

        expect($property->getDefaultValue())->toBe('permissions');
    });

    it('has correct title', function (): void {
        $reflection = new ReflectionClass(UserPermissionsRelationManager::class);
        $property = $reflection->getProperty('title');

        expect($property->getDefaultValue())->toBe('Direct Permissions');
    });

    it('extends RelationManager', function (): void {
        expect(is_subclass_of(UserPermissionsRelationManager::class, RelationManager::class))->toBeTrue();
    });

    it('has table method that configures columns', function (): void {
        $method = new ReflectionMethod(UserPermissionsRelationManager::class, 'table');

        expect($method->isPublic())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe(Table::class);
    });

    it('has form method that configures schema', function (): void {
        $method = new ReflectionMethod(UserPermissionsRelationManager::class, 'form');

        expect($method->isPublic())->toBeTrue();
        expect($method->getReturnType()->getName())->toBe(Schema::class);
    });
});
