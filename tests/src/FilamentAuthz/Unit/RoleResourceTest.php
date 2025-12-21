<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\RoleResource\RelationManagers\PermissionsRelationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use AIArmada\FilamentAuthz\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filament-authz.user_model' => User::class]);
    config(['filament-authz.super_admin_role' => 'super_admin']);
    config(['filament-authz.navigation.group' => 'Authorization']);
    config(['filament-authz.navigation.icons.roles' => 'heroicon-o-shield-check']);
    config(['filament-authz.navigation.sort' => 5]);
    config(['filament-authz.guards' => ['web', 'api']]);
});

describe('RoleResource', function (): void {
    it('returns correct model from config', function (): void {
        expect(RoleResource::getModel())->toBe(Role::class);
    });

    it('returns navigation group from config', function (): void {
        expect(RoleResource::getNavigationGroup())->toBe('Authorization');
    });

    it('returns navigation icon from config', function (): void {
        expect(RoleResource::getNavigationIcon())->toBe('heroicon-o-shield-check');
    });

    it('returns navigation sort from config', function (): void {
        expect(RoleResource::getNavigationSort())->toBe(5);
    });

    it('does not register navigation when user is not authenticated', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        expect(RoleResource::shouldRegisterNavigation())->toBeFalse();
    });

    it('registers navigation when user has role.viewAny permission', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('role.viewAny')
            ->andReturn(true);
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->andReturn(false);

        Auth::shouldReceive('user')->andReturn($user);

        expect(RoleResource::shouldRegisterNavigation())->toBeTrue();
    });

    it('registers navigation when user has super admin role', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('role.viewAny')
            ->andReturn(false);
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->andReturn(true);

        Auth::shouldReceive('user')->andReturn($user);

        expect(RoleResource::shouldRegisterNavigation())->toBeTrue();
    });

    it('does not register navigation when user lacks permission and role', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('role.viewAny')
            ->andReturn(false);
        $user->shouldReceive('hasRole')
            ->with('super_admin')
            ->andReturn(false);

        Auth::shouldReceive('user')->andReturn($user);

        expect(RoleResource::shouldRegisterNavigation())->toBeFalse();
    });

    it('returns correct pages', function (): void {
        $pages = RoleResource::getPages();

        expect($pages)->toHaveKeys(['index', 'create', 'edit']);
    });

    it('returns correct relations', function (): void {
        $relations = RoleResource::getRelations();

        expect($relations)->toContain(PermissionsRelationManager::class);
    });
});
