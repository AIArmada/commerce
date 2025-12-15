<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Resources\UserResource;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\PermissionsRelationManager;
use AIArmada\FilamentAuthz\Resources\UserResource\RelationManagers\RolesRelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('filament-authz.user_model', User::class);
    config()->set('filament-authz.navigation.group', 'Authorization');
    config()->set('filament-authz.navigation.icons.users', 'heroicon-o-users');
    config()->set('filament-authz.navigation.sort', 15);
    config()->set('filament-authz.super_admin_role', 'super-admin');
});

describe('UserResource', function (): void {
    it('returns correct model from config', function (): void {
        expect(UserResource::getModel())->toBe(User::class);
    });

    it('returns navigation group from config', function (): void {
        expect(UserResource::getNavigationGroup())->toBe('Authorization');
    });

    it('returns navigation icon from config', function (): void {
        expect(UserResource::getNavigationIcon())->toBe('heroicon-o-users');
    });

    it('returns navigation sort from config', function (): void {
        expect(UserResource::getNavigationSort())->toBe(15);
    });

    it('does not register navigation when user is not authenticated', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        expect(UserResource::shouldRegisterNavigation())->toBeFalse();
    });

    it('registers navigation when user has user.viewAny permission', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->with('user.viewAny')->andReturn(true);
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(false);

        Auth::shouldReceive('user')->andReturn($user);

        expect(UserResource::shouldRegisterNavigation())->toBeTrue();
    });

    it('registers navigation when user has super admin role', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->with('user.viewAny')->andReturn(false);
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(true);

        Auth::shouldReceive('user')->andReturn($user);

        expect(UserResource::shouldRegisterNavigation())->toBeTrue();
    });

    it('does not register navigation when user lacks permission and role', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->with('user.viewAny')->andReturn(false);
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(false);

        Auth::shouldReceive('user')->andReturn($user);

        expect(UserResource::shouldRegisterNavigation())->toBeFalse();
    });

    it('returns correct pages', function (): void {
        $pages = UserResource::getPages();

        expect($pages)->toHaveKeys(['index', 'create', 'edit']);
    });

    it('returns correct relations with roles and permissions', function (): void {
        $relations = UserResource::getRelations();

        expect($relations)
            ->toContain(RolesRelationManager::class)
            ->toContain(PermissionsRelationManager::class);
    });

    it('creates form schema without app user resource', function (): void {
        $form = Mockery::mock(Schema::class);
        $form->shouldReceive('schema')->once()->andReturnSelf();

        $result = UserResource::form($form);

        expect($result)->toBe($form);
    });

    it('creates table without app user resource', function (): void {
        $table = Mockery::mock(Table::class);
        $table->shouldReceive('columns')->once()->andReturnSelf();
        $table->shouldReceive('actions')->once()->andReturnSelf();

        $result = UserResource::table($table);

        expect($result)->toBe($table);
    });
});
