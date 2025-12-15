<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Console\AuthzCacheCommand;
use AIArmada\FilamentAuthz\Services\PermissionCacheService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    // Register the command if not already registered
    $this->app->singleton(AuthzCacheCommand::class);
});

test('authz:cache command with stats action shows statistics', function (): void {
    $cacheService = Mockery::mock(PermissionCacheService::class);
    $cacheService->shouldReceive('getStats')->once()->andReturn([
        'enabled' => true,
        'store' => 'array',
        'ttl' => 3600,
    ]);

    app()->instance(PermissionCacheService::class, $cacheService);

    $exitCode = Artisan::call('authz:cache', ['action' => 'stats']);

    expect($exitCode)->toBe(0);
});

test('authz:cache command with warm action warms cache', function (): void {
    $cacheService = Mockery::mock(PermissionCacheService::class);
    $cacheService->shouldReceive('warmRoleCache')->once();

    app()->instance(PermissionCacheService::class, $cacheService);

    $exitCode = Artisan::call('authz:cache', ['action' => 'warm']);

    expect($exitCode)->toBe(0);
});

test('authz:cache command with invalid action returns failure', function (): void {
    $cacheService = Mockery::mock(PermissionCacheService::class);
    app()->instance(PermissionCacheService::class, $cacheService);

    // Note: The test with invalid action returns FAILURE because no matching action
    $exitCode = Artisan::call('authz:cache', ['action' => 'invalid']);

    expect($exitCode)->toBe(1);
});

afterEach(function (): void {
    Mockery::close();
});
