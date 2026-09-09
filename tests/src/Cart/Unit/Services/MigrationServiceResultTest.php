<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Services\CartMigrationService;
use Illuminate\Support\Collection;
use Tests\Support\Cart\InMemoryStorage;

it('returns detailed result object when migrating for user succeeds', function (): void {
    config(['cart.events' => false]);

    $user = 123;

    $storage = new InMemoryStorage;
    $storage->putItems('session-id', 'default', [
        'item-1' => ['quantity' => 2],
        'item-2' => ['quantity' => 3],
    ]);

    $registry = new CartMergeStrategyRegistry;
    $registry->registerBuiltIns();
    $action = new MigrateGuestCartToUserAction($registry, $storage);
    $service = new CartMigrationService([], $storage, $action);

    $result = $service->migrateGuestCartForUser($user, 'default', 'session-id');

    expect($result->success)->toBeTrue();
    expect($result->itemsMerged)->toBe(5);
    expect($result->conflicts)->toBeInstanceOf(Collection::class);
    expect($result->message)->toBe('Cart migration completed successfully');
});

it('returns failure result object when no items to migrate', function (): void {
    config(['cart.events' => false]);

    $user = 123;

    $registry = new CartMergeStrategyRegistry;
    $registry->registerBuiltIns();
    $service = new CartMigrationService(
        migrationAction: new MigrateGuestCartToUserAction($registry),
    );

    $result = $service->migrateGuestCartForUser($user, 'default', 'session-id');

    expect($result->success)->toBeFalse();
    expect($result->itemsMerged)->toBe(0);
    expect($result->conflicts)->toBeInstanceOf(Collection::class);
    expect($result->message)->toBe('No items to migrate');
});
