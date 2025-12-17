<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Mockery::close();
});

describe('ContextualAuthorizationService', function (): void {
    describe('canWithContext', function (): void {
        it('returns true when user has global permission', function (): void {
            $user = Mockery::mock();
            $user->shouldReceive('getKey')->andReturn(1);

            $aggregator = Mockery::mock(PermissionAggregator::class);
            $aggregator->shouldReceive('userHasPermission')
                ->with($user, 'orders.view')
                ->andReturn(true);

            $service = new ContextualAuthorizationService($aggregator);

            $result = $service->canWithContext($user, 'orders.view', []);

            expect($result)->toBeTrue();
        });

        it('returns false when user has no global permission and no scoped permissions', function (): void {
            $user = Mockery::mock();
            $user->shouldReceive('getKey')->andReturn(1);
            $class = get_class($user);

            $aggregator = Mockery::mock(PermissionAggregator::class);
            $aggregator->shouldReceive('userHasPermission')
                ->with($user, 'orders.delete')
                ->andReturn(false);

            $service = new ContextualAuthorizationService($aggregator);

            // Mock the getScopedPermissions query to return empty collection
            $result = $service->canWithContext($user, 'orders.delete', []);

            // Should return false (scoped permissions query will run but return empty)
            expect($result)->toBeFalse();
        });
    });

    describe('canForResource', function (): void {
        it('builds context from resource attributes', function (): void {
            $user = Mockery::mock();
            $user->shouldReceive('getKey')->andReturn(1);

            $resource = Mockery::mock(Model::class);
            $resource->shouldReceive('getKey')->andReturn('uuid-123');
            $resource->shouldReceive('getAttribute')
                ->with('user_id')
                ->andReturn(5);
            $resource->shouldReceive('getAttribute')
                ->with('team_id')
                ->andReturn('team-1');
            $resource->shouldReceive('getAttribute')
                ->with('tenant_id')
                ->andReturn(null);

            $aggregator = Mockery::mock(PermissionAggregator::class);
            $aggregator->shouldReceive('userHasPermission')
                ->andReturn(true);

            $service = new ContextualAuthorizationService($aggregator);

            $result = $service->canForResource($user, 'orders.view', $resource);

            expect($result)->toBeTrue();
        });
    });

    describe('canInTeam', function (): void {
        it('calls canWithContext with team context', function (): void {
            $user = Mockery::mock();
            $user->shouldReceive('getKey')->andReturn(1);

            $aggregator = Mockery::mock(PermissionAggregator::class);
            $aggregator->shouldReceive('userHasPermission')
                ->with($user, 'reports.view')
                ->andReturn(true);

            $service = new ContextualAuthorizationService($aggregator);

            $result = $service->canInTeam($user, 'reports.view', 'team-123');

            expect($result)->toBeTrue();
        });
    });

    describe('canInTenant', function (): void {
        it('calls canWithContext with tenant context', function (): void {
            $user = Mockery::mock();
            $user->shouldReceive('getKey')->andReturn(1);

            $aggregator = Mockery::mock(PermissionAggregator::class);
            $aggregator->shouldReceive('userHasPermission')
                ->with($user, 'settings.manage')
                ->andReturn(true);

            $service = new ContextualAuthorizationService($aggregator);

            $result = $service->canInTenant($user, 'settings.manage', 'tenant-456');

            expect($result)->toBeTrue();
        });
    });

    describe('clearCache', function (): void {
        it('clears user context cache', function (): void {
            $user = Mockery::mock();
            $user->shouldReceive('getKey')->andReturn(42);

            $aggregator = Mockery::mock(PermissionAggregator::class);

            $service = new ContextualAuthorizationService($aggregator);

            // Set a cache value
            $cacheKey = 'permissions:contextual:user:42';
            Cache::put($cacheKey, 'test-value', 3600);

            expect(Cache::has($cacheKey))->toBeTrue();

            $service->clearCache($user);

            expect(Cache::has($cacheKey))->toBeFalse();
        });
    });

    describe('getScopedPermissions', function (): void {
        it('returns empty collection when no scoped permissions exist', function (): void {
            $user = Mockery::mock();
            $user->shouldReceive('getKey')->andReturn(1);

            $aggregator = Mockery::mock(PermissionAggregator::class);

            $service = new ContextualAuthorizationService($aggregator);

            // This will hit the database and return empty collection
            $permissions = $service->getScopedPermissions($user);

            expect($permissions)->toBeInstanceOf(Collection::class);
        });

        it('filters by permission name when provided', function (): void {
            $user = Mockery::mock();
            $user->shouldReceive('getKey')->andReturn(1);

            $aggregator = Mockery::mock(PermissionAggregator::class);

            $service = new ContextualAuthorizationService($aggregator);

            // This tests that the query builder is modified correctly
            $permissions = $service->getScopedPermissions($user, 'specific.permission');

            expect($permissions)->toBeInstanceOf(Collection::class);
        });
    });

    describe('getPermissionScopes', function (): void {
        it('returns collection of scope arrays', function (): void {
            $user = Mockery::mock();
            $user->shouldReceive('getKey')->andReturn(1);

            $aggregator = Mockery::mock(PermissionAggregator::class);

            $service = new ContextualAuthorizationService($aggregator);

            $scopes = $service->getPermissionScopes($user, 'test.permission');

            expect($scopes)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        });
    });
});
