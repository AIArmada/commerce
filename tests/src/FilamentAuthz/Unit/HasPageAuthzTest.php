<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Concerns\HasPageAuthz;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('filament-authz.super_admin_role', 'super-admin');
});

// Create a minimal test class using the trait
class TestPageWithAuthzConcern
{
    use HasPageAuthz;

    public function getTitle(): string
    {
        return 'Test Page';
    }

    public static function getSlug(): string
    {
        return 'test-page';
    }
}

describe('HasPageAuthz', function () {
    it('generates permission key from slug', function () {
        // Reset static state first using reflection
        $reflection = new ReflectionClass(TestPageWithAuthzConcern::class);
        $property = $reflection->getProperty('pagePermissionKey');
        $property->setAccessible(true);
        $property->setValue(null, null);

        expect(TestPageWithAuthzConcern::getPagePermissionKey())->toBe('page.test-page');
    });

    it('uses custom permission key when set', function () {
        TestPageWithAuthzConcern::setPagePermissionKey('custom.permission');

        expect(TestPageWithAuthzConcern::getPagePermissionKey())->toBe('custom.permission');

        // Reset using reflection
        $reflection = new ReflectionClass(TestPageWithAuthzConcern::class);
        $property = $reflection->getProperty('pagePermissionKey');
        $property->setAccessible(true);
        $property->setValue(null, null);
    });

    it('stores required permissions', function () {
        TestPageWithAuthzConcern::requirePermissions(['permission.view', 'permission.create']);

        $reflection = new ReflectionClass(TestPageWithAuthzConcern::class);
        $property = $reflection->getProperty('requiredPagePermissions');
        $property->setAccessible(true);

        expect($property->getValue())->toBe(['permission.view', 'permission.create']);

        // Reset
        TestPageWithAuthzConcern::requirePermissions([]);
    });

    it('stores required roles', function () {
        TestPageWithAuthzConcern::requireRoles(['admin', 'manager']);

        $reflection = new ReflectionClass(TestPageWithAuthzConcern::class);
        $property = $reflection->getProperty('requiredPageRoles');
        $property->setAccessible(true);

        expect($property->getValue())->toBe(['admin', 'manager']);

        // Reset
        TestPageWithAuthzConcern::requireRoles([]);
    });

    it('stores team scope configuration', function () {
        TestPageWithAuthzConcern::scopeToTeam('team_id');

        $reflection = new ReflectionClass(TestPageWithAuthzConcern::class);
        $property = $reflection->getProperty('teamPermissionScope');
        $property->setAccessible(true);

        expect($property->getValue())->toBe('team_id');

        // Reset static - set to null
        $property->setValue(null, null);
    });

    it('returns title with permission debug in local environment', function () {
        // Set page permission key first
        TestPageWithAuthzConcern::setPagePermissionKey('page.test-page');

        $page = new TestPageWithAuthzConcern;

        // Set app environment to local
        app()->detectEnvironment(fn () => 'local');

        $title = $page->getTitleWithPermissionDebug();

        expect($title)->toBe('Test Page [page.test-page]');
    });

    it('returns plain title in non-local environment', function () {
        // Set page permission key first
        TestPageWithAuthzConcern::setPagePermissionKey('page.test-page');

        $page = new TestPageWithAuthzConcern;

        // Set app environment to production
        app()->detectEnvironment(fn () => 'production');

        $title = $page->getTitleWithPermissionDebug();

        expect($title)->toBe('Test Page');
    });

    it('denies access when user is not authenticated', function () {
        // Mock Guard properly
        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn(null);

        Filament::shouldReceive('auth')->andReturn($guard);

        expect(TestPageWithAuthzConcern::canAccess())->toBeFalse();
    });

    it('allows access for super admin user', function () {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(true);

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn($user);

        Filament::shouldReceive('auth')->andReturn($guard);

        expect(TestPageWithAuthzConcern::canAccess())->toBeTrue();
    });

    it('checks standard permission via aggregator when not super admin', function () {
        // Ensure no required roles or permissions
        TestPageWithAuthzConcern::requireRoles([]);
        TestPageWithAuthzConcern::requirePermissions([]);

        // Reset team scope
        $reflection = new ReflectionClass(TestPageWithAuthzConcern::class);
        $property = $reflection->getProperty('teamPermissionScope');
        $property->setAccessible(true);
        $property->setValue(null, null);

        // Set permission key
        TestPageWithAuthzConcern::setPagePermissionKey('page.test-page');

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(false);

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn($user);

        Filament::shouldReceive('auth')->andReturn($guard);

        $aggregator = Mockery::mock(PermissionAggregator::class);
        $aggregator->shouldReceive('userHasPermission')
            ->with($user, 'page.test-page')
            ->andReturn(true);

        app()->instance(PermissionAggregator::class, $aggregator);

        expect(TestPageWithAuthzConcern::canAccess())->toBeTrue();
    });
});
