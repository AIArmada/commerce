<?php

declare(strict_types=1);

use AIArmada\Cart\Listeners\HandleUserLogin;
use AIArmada\Cart\Services\CartMigrationService;
use AIArmada\Cart\Support\LoginMigrationCacheKey;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;

describe('HandleUserLogin', function (): void {
    it('pulls old session id from cache and triggers migration on login', function (): void {
        $user = (object) ['email' => 'test@example.com'];
        $oldSessionId = 'old-session-123';
        $cacheKey = LoginMigrationCacheKey::make($user->email);

        Cache::put($cacheKey, $oldSessionId);

        $migrationResult = (object) [
            'success' => true,
            'itemsMerged' => 2,
            'conflicts' => [],
            'message' => 'Cart migrated!',
        ];

        $migrationService = Mockery::instanceMock(CartMigrationService::class);
        $migrationService->shouldReceive('migrateGuestCartForUser')
            ->with($user, 'default', $oldSessionId)
            ->andReturn($migrationResult)
            ->once();

        $listener = new HandleUserLogin($migrationService);

        $listener->handle(new Login('web', $user, false));

        expect(Cache::has($cacheKey))->toBeFalse();
        expect(session('cart_migration'))->toMatchArray([
            'items_merged' => 2,
            'has_conflicts' => false,
            'conflicts' => [],
            'message' => 'Cart migrated!',
        ]);
    });

    it('falls back to cached username when the user model also has an email', function (): void {
        $user = (object) [
            'email' => 'test@example.com',
            'username' => 'testuser',
        ];

        $oldSessionId = 'old-session-username';
        Cache::put(LoginMigrationCacheKey::make($user->username), $oldSessionId);

        $migrationResult = (object) [
            'success' => true,
            'itemsMerged' => 1,
            'conflicts' => [],
            'message' => 'Cart migrated by username!',
        ];

        $migrationService = Mockery::instanceMock(CartMigrationService::class);
        $migrationService->shouldReceive('migrateGuestCartForUser')
            ->with($user, 'default', $oldSessionId)
            ->andReturn($migrationResult)
            ->once();

        $listener = new HandleUserLogin($migrationService);

        $listener->handle(new Login('web', $user, false));

        expect(Cache::has(LoginMigrationCacheKey::make($user->username)))->toBeFalse();
        expect(session('cart_migration'))->toMatchArray([
            'items_merged' => 1,
            'has_conflicts' => false,
            'conflicts' => [],
            'message' => 'Cart migrated by username!',
        ]);
    });
});
