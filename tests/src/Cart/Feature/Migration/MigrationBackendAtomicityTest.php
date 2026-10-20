<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Commerce\Tests\Cart\Support\InterleavingStorage;

/**
 * CART-07 backend atomicity regression.
 *
 * The guest-to-user transfer must be atomic in the actual storage backend.
 * For in-memory storages a `DB::transaction()` rollback does nothing, so the
 * two-cart atomic boundary lives in the storage transfer capability: both
 * source and target revisions are validated, and target write plus source
 * consumption commit as one unit. A losing transfer must leave the winning
 * competing mutation untouched and must never merge stale content twice.
 */
function cart07BackendItem(string $id, int $quantity = 1): array
{
    return [
        'id' => $id,
        'name' => 'Item ' . $id,
        'price' => 100,
        'quantity' => $quantity,
        'attributes' => [],
        'conditions' => [],
    ];
}

dataset('cart07 in-memory storages', [
    'shipped testing double' => [static fn (): StorageInterface => new InMemoryStorage],
    'repository test double' => [static fn (): StorageInterface => new Tests\Support\Cart\InMemoryStorage],
]);

beforeEach(function (): void {
    config(['cart.events' => false]);
});

it('preserves a source item added concurrently and merges it exactly once', function (callable $factory): void {
    $store = $factory();
    $store->putItems('cart07-add-guest', 'default', ['a' => cart07BackendItem('a')], CartRevision::none());

    // The competing source write commits after the merge read the source
    // snapshot, so the first transfer attempt loses its source revision.
    $interleaved = new InterleavingStorage($store);
    $interleaved->interleaveAfter('commitMergeTransfer', function () use ($store): void {
        $snapshot = $store->getSnapshot('cart07-add-guest', 'default');
        $items = $snapshot?->items ?? [];
        $items['b'] = cart07BackendItem('b');

        $store->putItems('cart07-add-guest', 'default', $items, $snapshot?->revision() ?? CartRevision::none());
    });

    $action = new MigrateGuestCartToUserAction(app(CartMergeStrategyRegistry::class), $interleaved);

    expect($action->execute('cart07-add-user', 'default', 'cart07-add-guest'))->toBeTrue();

    $targetItems = $store->getItems('cart07-add-user', 'default');

    // The winner's B is merged exactly once: the stale attempt must not have
    // written A into the target, or the retry would double A to quantity 2.
    expect(array_keys($targetItems))->toEqualCanonicalizing(['a', 'b'])
        ->and($targetItems['a']['quantity'])->toBe(1)
        ->and($targetItems['b']['quantity'])->toBe(1)
        ->and($store->getItems('cart07-add-guest', 'default'))->toBe([])
        ->and($store->isExpired('cart07-add-guest', 'default'))->toBeTrue()
        // v1 seed -> v2 competing add -> v3 guarded consumption.
        ->and($store->getVersion('cart07-add-guest', 'default'))->toBe(3);
})->with('cart07 in-memory storages');

it('keeps competing source content exactly once when merging into an existing target', function (callable $factory): void {
    $store = $factory();
    $store->putItems('cart07-overlap-target', 'default', ['seed' => cart07BackendItem('seed')], CartRevision::none());
    $store->putItems('cart07-overlap-guest', 'default', ['a' => cart07BackendItem('a')], CartRevision::none());
    $store->putMetadataBatch('cart07-overlap-target', 'default', ['owner_note' => 'target']);
    $store->putMetadataBatch('cart07-overlap-guest', 'default', ['guest_note' => 'guest']);

    $interleaved = new InterleavingStorage($store);
    $interleaved->interleaveAfter('commitMergeTransfer', function () use ($store): void {
        $snapshot = $store->getSnapshot('cart07-overlap-guest', 'default');
        $items = $snapshot?->items ?? [];
        $items['b'] = cart07BackendItem('b');

        $store->putItems('cart07-overlap-guest', 'default', $items, $snapshot?->revision() ?? CartRevision::none());
    });

    $action = new MigrateGuestCartToUserAction(app(CartMergeStrategyRegistry::class), $interleaved);

    expect($action->execute('cart07-overlap-target', 'default', 'cart07-overlap-guest'))->toBeTrue();

    $targetItems = $store->getItems('cart07-overlap-target', 'default');
    $targetMetadata = $store->getAllMetadata('cart07-overlap-target', 'default');

    expect($targetItems['seed']['quantity'])->toBe(1)
        ->and($targetItems['a']['quantity'])->toBe(1)
        ->and($targetItems['b']['quantity'])->toBe(1)
        ->and($targetMetadata['owner_note'])->toBe('target')
        ->and($targetMetadata['guest_note'])->toBe('guest')
        ->and($store->getItems('cart07-overlap-guest', 'default'))->toBe([]);
})->with('cart07 in-memory storages');

it('keeps a losing consumer from leaving any target cart behind', function (callable $factory): void {
    $store = $factory();
    $store->putItems('cart07-loser-guest', 'default', ['a' => cart07BackendItem('a')], CartRevision::none());

    $action = new MigrateGuestCartToUserAction(app(CartMergeStrategyRegistry::class), $store);

    expect($action->execute('cart07-winner', 'default', 'cart07-loser-guest'))->toBeTrue()
        ->and($action->execute('cart07-loser', 'default', 'cart07-loser-guest'))->toBeFalse()
        ->and($store->getItems('cart07-winner', 'default'))->toHaveKey('a')
        ->and($store->getItems('cart07-winner', 'default')['a']['quantity'])->toBe(1)
        ->and($store->has('cart07-loser', 'default'))->toBeFalse()
        ->and($store->getItems('cart07-loser-guest', 'default'))->toBe([])
        // v1 seed -> v2 the single committed consumption.
        ->and($store->getVersion('cart07-loser-guest', 'default'))->toBe(2);
})->with('cart07 in-memory storages');
