<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\MigrateCartOnLoginAction;
use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Services\CartMigrationService;
use AIArmada\Cart\Storage\CartRevision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Tests\Support\Cart\InMemoryStorage;

it('returns detailed result object when migrating for user succeeds', function (): void {
    config(['cart.events' => false]);

    $user = 123;

    $storage = new InMemoryStorage;
    $storage->putItems('session-id', 'default', [
        'item-1' => ['quantity' => 2],
        'item-2' => ['quantity' => 3],
    ], CartRevision::none());

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

it('rejects an empty user key instead of migrating into a truncated identity', function (): void {
    config(['cart.events' => false]);

    $storage = new InMemoryStorage;
    $storage->putItems('session-id', 'default', [
        'item-1' => ['quantity' => 1],
    ], CartRevision::none());

    $registry = new CartMergeStrategyRegistry;
    $registry->registerBuiltIns();
    $service = new CartMigrationService([], $storage, new MigrateGuestCartToUserAction($registry, $storage));

    expect(fn () => $service->migrateGuestCartToUser('', 'default', 'session-id'))
        ->toThrow(InvalidArgumentException::class);
});

it('returns a clear failure when the user model has no ID', function (): void {
    config(['cart.events' => false]);

    $storage = new InMemoryStorage;
    $storage->putItems('session-id', 'default', [
        'item-1' => ['quantity' => 1],
    ], CartRevision::none());

    $registry = new CartMergeStrategyRegistry;
    $registry->registerBuiltIns();
    $service = new CartMigrationService([], $storage, new MigrateGuestCartToUserAction($registry, $storage));

    $result = $service->migrateGuestCartForUser(new class extends Model {}, 'default', 'session-id');

    expect($result->success)->toBeFalse()
        ->and($result->itemsMerged)->toBe(0)
        ->and($result->message)->toBe('Cannot migrate cart: the user has no usable ID.');
});

it('fails clearly in login migration when the user has no usable ID', function (): void {
    $registry = new CartMergeStrategyRegistry;
    $registry->registerBuiltIns();
    $action = new MigrateCartOnLoginAction(new MigrateGuestCartToUserAction($registry));

    $result = $action->execute(new class {}, 'default', 'session-id');

    expect($result['success'])->toBeFalse()
        ->and($result['itemsMerged'])->toBe(0)
        ->and($result['message'])->toBe('Cannot migrate cart: the user has no usable ID.');
});
