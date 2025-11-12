<?php

declare(strict_types=1);

use AIArmada\Cart\Storage\SessionStorage;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

test('session storage clearMetadata actually clears', function (): void {
    $session = new Store('test', new ArraySessionHandler(60));
    $storage = new SessionStorage($session);

    $identifier = 'test-user';
    $instance = 'default';

    // Put some metadata
    $storage->putMetadata($identifier, $instance, 'key1', 'value1');
    $storage->putMetadata($identifier, $instance, 'key2', 'value2');

    // Verify it's there
    expect($storage->getMetadata($identifier, $instance, 'key1'))->toBe('value1');
    expect($storage->getMetadata($identifier, $instance, 'key2'))->toBe('value2');

    // Clear metadata
    $storage->clearMetadata($identifier, $instance);

    // Verify it's gone
    expect($storage->getMetadata($identifier, $instance, 'key1'))->toBeNull();
    expect($storage->getMetadata($identifier, $instance, 'key2'))->toBeNull();
});
