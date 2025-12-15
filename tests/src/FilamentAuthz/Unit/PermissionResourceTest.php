<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\PermissionResource\RelationManagers\RolesRelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('filament-authz.navigation.group', 'Authorization');
    config()->set('filament-authz.navigation.icons.permissions', 'heroicon-o-key');
    config()->set('filament-authz.navigation.sort', 10);
    config()->set('filament-authz.super_admin_role', 'super-admin');
    config()->set('filament-authz.guards', ['web', 'api']);
    config()->set('permission.models.permission', Permission::class);
});

it('returns correct model from config', function (): void {
    expect(PermissionResource::getModel())->toBe(Permission::class);
});

it('returns navigation group from config', function (): void {
    expect(PermissionResource::getNavigationGroup())->toBe('Authorization');
});

it('returns navigation icon from config', function (): void {
    expect(PermissionResource::getNavigationIcon())->toBe('heroicon-o-key');
});

it('returns navigation sort from config', function (): void {
    expect(PermissionResource::getNavigationSort())->toBe(10);
});

it('does not register navigation when user is not authenticated', function (): void {
    Auth::shouldReceive('user')->andReturn(null);

    expect(PermissionResource::shouldRegisterNavigation())->toBeFalse();
});

it('registers navigation when user has permission.viewAny permission', function (): void {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with('permission.viewAny')->andReturn(true);
    $user->shouldReceive('hasRole')->with('super-admin')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    expect(PermissionResource::shouldRegisterNavigation())->toBeTrue();
});

it('registers navigation when user has super admin role', function (): void {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with('permission.viewAny')->andReturn(false);
    $user->shouldReceive('hasRole')->with('super-admin')->andReturn(true);

    Auth::shouldReceive('user')->andReturn($user);

    expect(PermissionResource::shouldRegisterNavigation())->toBeTrue();
});

it('does not register navigation when user lacks permission and role', function (): void {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with('permission.viewAny')->andReturn(false);
    $user->shouldReceive('hasRole')->with('super-admin')->andReturn(false);

    Auth::shouldReceive('user')->andReturn($user);

    expect(PermissionResource::shouldRegisterNavigation())->toBeFalse();
});

it('returns correct pages', function (): void {
    $pages = PermissionResource::getPages();

    expect($pages)->toHaveKeys(['index', 'create', 'edit']);
});

it('returns correct relations', function (): void {
    $relations = PermissionResource::getRelations();

    expect($relations)->toContain(RolesRelationManager::class);
});

it('creates form schema', function (): void {
    $form = Mockery::mock(Schema::class);
    $form->shouldReceive('schema')->once()->andReturnSelf();

    $result = PermissionResource::form($form);

    expect($result)->toBe($form);
});

it('creates table with columns and filters', function (): void {
    $table = Mockery::mock(Table::class);
    $table->shouldReceive('columns')->once()->andReturnSelf();
    $table->shouldReceive('filters')->once()->andReturnSelf();
    $table->shouldReceive('actions')->once()->andReturnSelf();
    $table->shouldReceive('bulkActions')->once()->andReturnSelf();

    $result = PermissionResource::table($table);

    expect($result)->toBe($table);
});
