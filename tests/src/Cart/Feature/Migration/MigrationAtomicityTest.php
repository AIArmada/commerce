<?php

declare(strict_types=1);

use AIArmada\Cart\Actions\MigrateGuestCartToUserAction;
use AIArmada\Cart\Exceptions\InvalidCartItemException;
use AIArmada\Cart\Services\CartMergeStrategyRegistry;
use AIArmada\Cart\Storage\DatabaseStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cart.events' => false]);

    $this->storage = new DatabaseStorage(app('db')->connection(), 'carts');
    $this->action = new MigrateGuestCartToUserAction(app(CartMergeStrategyRegistry::class));
});

it('rolls back every merge write when a later write fails', function (): void {
    config(['cart.limits.max_data_size_bytes' => 250]);

    $userItems = [
        'user-product' => [
            'id' => 'user-product',
            'name' => 'User Product',
            'price' => 100,
            'quantity' => 1,
            'attributes' => [],
        ],
    ];
    $guestItems = [
        'guest-product' => [
            'id' => 'guest-product',
            'name' => 'Guest Product',
            'price' => 200,
            'quantity' => 1,
            'attributes' => [],
        ],
    ];

    $this->storage->putItems('atomic-user', 'default', $userItems);
    $this->storage->putMetadataBatch('atomic-user', 'default', ['user_note' => str_repeat('u', 150)]);
    $this->storage->putItems('atomic-guest', 'default', $guestItems);
    $this->storage->putMetadataBatch('atomic-guest', 'default', ['guest_note' => str_repeat('g', 150)]);

    // Merged metadata exceeds the cap, so the batch write fails AFTER items merged.
    expect(fn (): bool => $this->action->execute('atomic-user', 'default', 'atomic-guest'))
        ->toThrow(InvalidArgumentException::class);

    // The whole merge rolled back: user items hold no guest rows, guest intact.
    expect($this->storage->getItems('atomic-user', 'default'))->toEqual($userItems)
        ->and($this->storage->getItems('atomic-guest', 'default'))->toEqual($guestItems);
});

it('clamps merged quantities to the configured maximum', function (): void {
    config(['cart.limits.max_item_quantity' => 5]);

    $this->storage->putItems('clamp-user', 'default', [
        'shared' => ['id' => 'shared', 'name' => 'Shared', 'price' => 100, 'quantity' => 4, 'attributes' => []],
    ]);
    $this->storage->putItems('clamp-guest', 'default', [
        'shared' => ['id' => 'shared', 'name' => 'Shared', 'price' => 100, 'quantity' => 4, 'attributes' => []],
    ]);

    expect($this->action->execute('clamp-user', 'default', 'clamp-guest'))->toBeTrue();

    $merged = $this->storage->getItems('clamp-user', 'default');

    expect($merged['shared']['quantity'])->toBe(5)
        ->and($this->storage->getItems('clamp-guest', 'default'))->toBeEmpty();
});

it('rejects corrupt guest rows without writing anything', function (): void {
    $userItems = [
        'user-product' => ['id' => 'user-product', 'name' => 'User', 'price' => 100, 'quantity' => 1, 'attributes' => []],
    ];
    $guestItems = [
        'broken' => 'not-an-array',
        'ok' => ['id' => 'ok', 'name' => 'Ok', 'price' => 100, 'quantity' => 1, 'attributes' => []],
    ];

    $this->storage->putItems('corrupt-user', 'default', $userItems);
    $this->storage->putItems('corrupt-guest', 'default', $guestItems);

    expect(fn (): bool => $this->action->execute('corrupt-user', 'default', 'corrupt-guest'))
        ->toThrow(InvalidCartItemException::class);

    expect($this->storage->getItems('corrupt-user', 'default'))->toEqual($userItems)
        ->and($this->storage->getItems('corrupt-guest', 'default'))->toEqual($guestItems);
});
