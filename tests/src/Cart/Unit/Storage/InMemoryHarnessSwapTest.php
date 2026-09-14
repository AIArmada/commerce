<?php

declare(strict_types=1);

use Tests\Support\Cart\InMemoryStorage;

it('refuses to swap into an occupied target and leaves both carts untouched', function (): void {
    $storage = new InMemoryStorage;

    $sourceItems = ['source-product' => ['id' => 'source-product', 'quantity' => 2]];
    $sourceConditions = ['source-deal' => ['name' => 'source-deal']];
    $targetItems = ['target-product' => ['id' => 'target-product', 'quantity' => 1]];
    $targetConditions = ['target-deal' => ['name' => 'target-deal']];

    $storage->putBoth('guest-session', 'default', $sourceItems, $sourceConditions);
    $storage->putMetadata('guest-session', 'default', 'note', 'guest-note');
    $storage->putBoth('user-7', 'default', $targetItems, $targetConditions);
    $storage->putMetadata('user-7', 'default', 'note', 'user-note');

    expect($storage->swapIdentifier('guest-session', 'user-7', 'default'))->toBeFalse();

    expect($storage->getItems('guest-session', 'default'))->toEqual($sourceItems)
        ->and($storage->getConditions('guest-session', 'default'))->toEqual($sourceConditions)
        ->and($storage->getMetadata('guest-session', 'default', 'note'))->toBe('guest-note')
        ->and($storage->getItems('user-7', 'default'))->toEqual($targetItems)
        ->and($storage->getConditions('user-7', 'default'))->toEqual($targetConditions)
        ->and($storage->getMetadata('user-7', 'default', 'note'))->toBe('user-note');
});

it('treats swapping an identifier onto itself as a no-op', function (): void {
    $storage = new InMemoryStorage;

    $items = ['product' => ['id' => 'product', 'quantity' => 2]];
    $conditions = ['deal' => ['name' => 'deal']];

    $storage->putBoth('session-1', 'default', $items, $conditions);

    expect($storage->swapIdentifier('session-1', 'session-1', 'default'))->toBeTrue();

    expect($storage->getItems('session-1', 'default'))->toEqual($items)
        ->and($storage->getConditions('session-1', 'default'))->toEqual($conditions);

    expect($storage->swapIdentifier('missing-session', 'missing-session', 'default'))->toBeFalse();
});

it('transfers the cart when the target identifier is free', function (): void {
    $storage = new InMemoryStorage;

    $items = ['product' => ['id' => 'product', 'quantity' => 2]];

    $storage->putBoth('guest-session', 'default', $items, []);

    expect($storage->swapIdentifier('guest-session', 'user-7', 'default'))->toBeTrue();

    expect($storage->getItems('guest-session', 'default'))->toBeEmpty()
        ->and($storage->getItems('user-7', 'default'))->toEqual($items);
});
