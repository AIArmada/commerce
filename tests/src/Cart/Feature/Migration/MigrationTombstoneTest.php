<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;
use AIArmada\Cart\Models\CartModel;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Storage\DatabaseStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cart.events' => false]);

    $this->storage = new DatabaseStorage(app('db')->connection(), 'carts');
    $this->action = new MigrateGuestCartToUserAction(app(CartMergeStrategyRegistry::class));
});

it('keeps a merged tombstone with attribution when the user cart is empty', function (): void {
    $guestItems = [
        'guest-product' => ['id' => 'guest-product', 'name' => 'Guest Product', 'price' => 100, 'quantity' => 2, 'attributes' => []],
    ];

    $this->storage->putItems('tombstone-guest', 'default', $guestItems);
    $this->storage->putConditions('tombstone-guest', 'default', ['deal' => ['name' => 'deal']]);
    $this->storage->putMetadataBatch('tombstone-guest', 'default', ['note' => 'guest-note']);

    expect($this->action->execute('tombstone-user', 'default', 'tombstone-guest'))->toBeTrue();

    $target = DB::table('carts')->where('identifier', 'tombstone-user')->where('instance', 'default')->first();
    $source = DB::table('carts')->where('identifier', 'tombstone-guest')->where('instance', 'default')->first();

    expect($source)->not->toBeNull()
        ->and($source->merged_into_id)->toBe($target->id)
        ->and($source->expired_at)->not->toBeNull()
        ->and($this->storage->getItems('tombstone-guest', 'default'))->toBeEmpty()
        ->and($this->storage->getConditions('tombstone-guest', 'default'))->toBeEmpty()
        ->and($this->storage->getAllMetadata('tombstone-guest', 'default'))->toBeEmpty()
        ->and($this->storage->getItems('tombstone-user', 'default'))->toEqual($guestItems);

    $tombstone = CartModel::query()->where('identifier', 'tombstone-guest')->where('instance', 'default')->first();

    expect($tombstone->isMerged())->toBeTrue()
        ->and($tombstone->isExpired())->toBeTrue()
        ->and($tombstone->isEmpty())->toBeTrue();

    expect($this->action->execute('tombstone-user', 'default', 'tombstone-guest'))->toBeFalse()
        ->and($this->storage->getItems('tombstone-user', 'default'))->toEqual($guestItems);
});

it('keeps a merged tombstone with attribution when merging into an existing user cart', function (): void {
    $this->storage->putItems('merge-user', 'default', [
        'shared' => ['id' => 'shared', 'name' => 'Shared', 'price' => 100, 'quantity' => 1, 'attributes' => []],
    ]);
    $this->storage->putItems('merge-guest', 'default', [
        'shared' => ['id' => 'shared', 'name' => 'Shared', 'price' => 100, 'quantity' => 2, 'attributes' => []],
    ]);

    expect($this->action->execute('merge-user', 'default', 'merge-guest'))->toBeTrue();

    $target = DB::table('carts')->where('identifier', 'merge-user')->where('instance', 'default')->first();
    $source = DB::table('carts')->where('identifier', 'merge-guest')->where('instance', 'default')->first();

    expect($source)->not->toBeNull()
        ->and($source->merged_into_id)->toBe($target->id)
        ->and($source->expired_at)->not->toBeNull()
        ->and($this->storage->getItems('merge-guest', 'default'))->toBeEmpty()
        ->and($this->storage->getItems('merge-user', 'default')['shared']['quantity'])->toBe(3);

    $tombstone = CartModel::query()->where('identifier', 'merge-guest')->where('instance', 'default')->first();

    expect($tombstone->isMerged())->toBeTrue()
        ->and($tombstone->isExpired())->toBeTrue()
        ->and($tombstone->isEmpty())->toBeTrue();
});
