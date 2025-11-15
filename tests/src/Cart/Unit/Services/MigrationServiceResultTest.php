<?php

declare(strict_types=1);

use AIArmada\Cart\Services\CartMigrationService;

it('returns detailed result object when migrating for user succeeds', function (): void {
    $user = new class
    {
        public int $id = 123;
    };

    $service = new class extends CartMigrationService
    {
        public bool $called = false;

        public function migrateGuestCartToUser(string|int $userId, string $instance, string $sessionId): bool
        {
            $this->called = true;

            return true;
        }
    };

    $result = $service->migrateGuestCartForUser($user, 'default', 'session-id');

    expect($service->called)->toBeTrue();
    expect($result->success)->toBeTrue();
    expect($result->itemsMerged)->toBe(1);
    expect($result->conflicts)->toBeInstanceOf(Illuminate\Support\Collection::class);
    expect($result->message)->toBe('Cart migration completed successfully');
});

it('returns failure result object when no items to migrate', function (): void {
    $user = new class
    {
        public int $id = 123;
    };

    $service = new class extends CartMigrationService
    {
        public bool $called = false;

        public function migrateGuestCartToUser(string|int $userId, string $instance, string $sessionId): bool
        {
            $this->called = true;

            return false;
        }
    };

    $result = $service->migrateGuestCartForUser($user, 'default', 'session-id');

    expect($service->called)->toBeTrue();
    expect($result->success)->toBeFalse();
    expect($result->itemsMerged)->toBe(0);
    expect($result->conflicts)->toBeInstanceOf(Illuminate\Support\Collection::class);
    expect($result->message)->toBe('No items to migrate');
});
