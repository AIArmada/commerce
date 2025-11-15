<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Storage\CacheStorage;
use AIArmada\Cart\Storage\SessionStorage;
use Illuminate\Support\Facades\Cache;

it('returns null for version and id in non-persistent storages', function (): void {
    $sessionStore = new Illuminate\Session\Store('testing', new Illuminate\Session\ArraySessionHandler(120));
    $sessionStorage = new SessionStorage($sessionStore);

    expect($sessionStorage->getVersion('id', 'default'))->toBeNull();
    expect($sessionStorage->getId('id', 'default'))->toBeNull();

    $cacheStorage = new CacheStorage(Cache::store());
    expect($cacheStorage->getVersion('id', 'default'))->toBeNull();
    expect($cacheStorage->getId('id', 'default'))->toBeNull();
});

it('exposes version and id via cart facade for database storage', function (): void {
    config(['cart.storage' => 'database']);
    Cart::clear();

    Cart::add('versioned-item', 'Versioned Item', 100.00, 1);

    $version = Cart::getVersion();
    $id = Cart::getId();

    expect($version)->not->toBeNull();
    expect($version)->toBeInt();
    expect($id)->not->toBeNull();
    expect($id)->toBeString();

    // Updating the cart should bump the version
    $oldVersion = $version;
    Cart::update('versioned-item', ['quantity' => 2]);

    $newVersion = Cart::getVersion();
    expect($newVersion)->toBeGreaterThanOrEqual($oldVersion);
});
