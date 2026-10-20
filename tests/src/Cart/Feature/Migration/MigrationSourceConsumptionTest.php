<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * CART-04 source consumption contract (sequential semantics).
 *
 * The genuine read/consume races live in the cart engine suite
 * ({@see /tests/src/Cart/Feature/Engine/CartMergeSourceRaceTest.php}): a
 * competing write performed inside the migration transaction is rolled back
 * by the retry, so it can only be proven on independent connections. This
 * file covers the sequential guarantees: repeat calls are no-ops and the
 * owner boundary is never crossed.
 */
function migrationSourceItem(string $id, int $quantity = 1): array
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

beforeEach(function (): void {
    config(['cart.events' => false]);

    $this->storage = new DatabaseStorage(app('db')->connection(), 'carts');
    $this->registry = app(CartMergeStrategyRegistry::class);
});

it('only consumes the source once when a second consumer runs after the first consumed it', function (): void {
    $storage = $this->storage;

    $storage->putItems('src-once-guest', 'default', [
        'a' => migrationSourceItem('a'),
    ], CartRevision::none());
    $storage->putMetadataBatch('src-once-guest', 'default', ['note' => 'guest']);

    $action = new MigrateGuestCartToUserAction($this->registry, $storage);

    expect($action->execute('src-once-user-a', 'default', 'src-once-guest'))->toBeTrue()
        ->and($action->execute('src-once-user-b', 'default', 'src-once-guest'))->toBeFalse()
        ->and($storage->getItems('src-once-user-a', 'default'))->toHaveKey('a')
        ->and($storage->getItems('src-once-user-b', 'default'))->toBeEmpty();

    $sourceRow = DB::table('carts')->where('identifier', 'src-once-guest')->where('instance', 'default')->first();
    $winnerRow = DB::table('carts')->where('identifier', 'src-once-user-a')->where('instance', 'default')->first();

    expect($sourceRow->merged_into_id)->toBe($winnerRow->id)
        ->and($sourceRow->expired_at)->not->toBeNull();
});

it('leaves an owner-scoped source cart untouched when migrating in owner mode', function (): void {
    $user = User::query()->create([
        'name' => 'Migration Source Owner',
        'email' => 'migration-source-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerStorage = $this->storage->withOwner($user);

    $ownerStorage->putItems('src-owner-guest', 'default', [
        'a' => migrationSourceItem('a'),
    ], CartRevision::none());

    $action = new MigrateGuestCartToUserAction($this->registry, $ownerStorage);

    expect($action->execute('src-owner-user', 'default', 'src-owner-guest'))->toBeFalse()
        ->and($ownerStorage->getItems('src-owner-guest', 'default'))->toHaveKey('a')
        ->and($ownerStorage->has('src-owner-user', 'default'))->toBeFalse();
});
