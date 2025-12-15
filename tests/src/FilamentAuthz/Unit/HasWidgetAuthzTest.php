<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Concerns\HasWidgetAuthz;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('filament-authz.super_admin_role', 'super-admin');
});

// Create a minimal test class using the trait
class TestWidgetWithAuthzConcern
{
    use HasWidgetAuthz;
}

describe('HasWidgetAuthz', function () {
    it('generates permission key from class name', function () {
        // Reset static state first using reflection
        $reflection = new ReflectionClass(TestWidgetWithAuthzConcern::class);
        $property = $reflection->getProperty('widgetPermissionKey');
        $property->setAccessible(true);
        $property->setValue(null, null);

        expect(TestWidgetWithAuthzConcern::getWidgetPermissionKey())->toBe('widget.test_widget_with_authz_concern');
    });

    it('uses custom permission key when set', function () {
        TestWidgetWithAuthzConcern::setWidgetPermissionKey('widget.custom');

        expect(TestWidgetWithAuthzConcern::getWidgetPermissionKey())->toBe('widget.custom');

        // Reset using reflection
        $reflection = new ReflectionClass(TestWidgetWithAuthzConcern::class);
        $property = $reflection->getProperty('widgetPermissionKey');
        $property->setAccessible(true);
        $property->setValue(null, null);
    });

    it('stores required widget permissions', function () {
        TestWidgetWithAuthzConcern::requireWidgetPermissions(['permission.view', 'permission.create']);

        $reflection = new ReflectionClass(TestWidgetWithAuthzConcern::class);
        $property = $reflection->getProperty('requiredWidgetPermissions');
        $property->setAccessible(true);

        expect($property->getValue())->toBe(['permission.view', 'permission.create']);

        // Reset
        TestWidgetWithAuthzConcern::requireWidgetPermissions([]);
    });

    it('stores required widget roles', function () {
        TestWidgetWithAuthzConcern::requireWidgetRoles(['admin', 'manager']);

        $reflection = new ReflectionClass(TestWidgetWithAuthzConcern::class);
        $property = $reflection->getProperty('requiredWidgetRoles');
        $property->setAccessible(true);

        expect($property->getValue())->toBe(['admin', 'manager']);

        // Reset
        TestWidgetWithAuthzConcern::requireWidgetRoles([]);
    });

    it('stores widget team scope configuration', function () {
        TestWidgetWithAuthzConcern::scopeWidgetToTeam('team_id');

        $reflection = new ReflectionClass(TestWidgetWithAuthzConcern::class);
        $property = $reflection->getProperty('widgetTeamScope');
        $property->setAccessible(true);

        expect($property->getValue())->toBe('team_id');

        // Reset
        $property->setValue(null, null);
    });

    it('sets hide when unauthorized to false when showing placeholder', function () {
        TestWidgetWithAuthzConcern::showPlaceholderWhenUnauthorized();

        $reflection = new ReflectionClass(TestWidgetWithAuthzConcern::class);
        $property = $reflection->getProperty('hideWhenUnauthorized');
        $property->setAccessible(true);

        expect($property->getValue())->toBeFalse();

        // Reset
        $property->setValue(null, true);
    });

    it('denies view when user is not authenticated', function () {
        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn(null);

        Filament::shouldReceive('auth')->andReturn($guard);

        expect(TestWidgetWithAuthzConcern::canView())->toBeFalse();
    });

    it('allows view for super admin user', function () {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(true);

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn($user);

        Filament::shouldReceive('auth')->andReturn($guard);

        expect(TestWidgetWithAuthzConcern::canView())->toBeTrue();
    });

    it('checks widget permission via aggregator when not super admin', function () {
        // Ensure no required roles or permissions
        TestWidgetWithAuthzConcern::requireWidgetRoles([]);
        TestWidgetWithAuthzConcern::requireWidgetPermissions([]);

        // Reset team scope
        $reflection = new ReflectionClass(TestWidgetWithAuthzConcern::class);
        $teamProperty = $reflection->getProperty('widgetTeamScope');
        $teamProperty->setAccessible(true);
        $teamProperty->setValue(null, null);

        // Reset permission key
        $permProperty = $reflection->getProperty('widgetPermissionKey');
        $permProperty->setAccessible(true);
        $permProperty->setValue(null, null);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(false);

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn($user);

        Filament::shouldReceive('auth')->andReturn($guard);

        $aggregator = Mockery::mock(PermissionAggregator::class);
        $aggregator->shouldReceive('userHasPermission')
            ->with($user, 'widget.test_widget_with_authz_concern')
            ->andReturn(true);

        app()->instance(PermissionAggregator::class, $aggregator);

        expect(TestWidgetWithAuthzConcern::canView())->toBeTrue();
    });

    it('denies view when user lacks required roles', function () {
        TestWidgetWithAuthzConcern::requireWidgetRoles(['admin', 'manager']);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(false);
        $user->shouldReceive('hasAnyRole')->with(['admin', 'manager'])->andReturn(false);

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn($user);

        Filament::shouldReceive('auth')->andReturn($guard);

        expect(TestWidgetWithAuthzConcern::canView())->toBeFalse();

        // Reset
        TestWidgetWithAuthzConcern::requireWidgetRoles([]);
    });
});
