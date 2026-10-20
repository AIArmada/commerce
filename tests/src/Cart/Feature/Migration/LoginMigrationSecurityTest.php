<?php

declare(strict_types=1);

use AIArmada\Cart\Listeners\HandleUserLogin;
use AIArmada\Cart\Listeners\HandleUserLoginAttempt;
use AIArmada\Cart\Storage\CartRevision;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\CommerceSupport\Support\OwnerCache;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cart.events' => false]);
    session()->forget(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY);
});

it('migrates the guest cart through the session stash on login', function (): void {
    $storage = new DatabaseStorage(app('db')->connection(), 'carts');
    $guestSessionId = session()->getId();

    $storage->putItems($guestSessionId, 'default', [
        'product-1' => [
            'id' => 'product-1',
            'name' => 'Guest Product',
            'price' => 1000,
            'quantity' => 2,
            'attributes' => [],
        ],
    ], CartRevision::none());

    // Login attempt stashes the guest session id in the guest's OWN session.
    (new HandleUserLoginAttempt)->handle(
        new Attempting('web', ['email' => 'victim@example.com', 'password' => 'secret'], false)
    );

    expect(session(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY))->toBe($guestSessionId);

    $user = new class
    {
        public $id = 7;

        public $email = 'victim@example.com';
    };

    app(HandleUserLogin::class)->handle(new Login('web', $user, false));

    expect($storage->getItems('7', 'default'))->toHaveCount(1)
        ->and($storage->getItems($guestSessionId, 'default'))->toBeEmpty()
        ->and(session(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY))->toBeNull();
});

it('ignores identifier-keyed cache entries planted by another session', function (): void {
    $storage = new DatabaseStorage(app('db')->connection(), 'carts');

    // Attacker cart plus a planted entry under the legacy identifier-keyed format.
    $storage->putItems('attacker-session', 'default', [
        'evil' => [
            'id' => 'evil',
            'name' => 'Planted Product',
            'price' => 1,
            'quantity' => 1,
            'attributes' => [],
        ],
    ], CartRevision::none());

    $plantedKey = OwnerCache::key(null, 'cart.migration.' . hash('sha256', 'victim@example.com'));
    Cache::put($plantedKey, 'attacker-session');

    $user = new class
    {
        public $id = 9;

        public $email = 'victim@example.com';
    };

    app(HandleUserLogin::class)->handle(new Login('web', $user, false));

    // Nothing merges: the victim cart stays empty and the attacker cart is untouched.
    expect($storage->getItems('9', 'default'))->toBeEmpty()
        ->and($storage->getItems('attacker-session', 'default'))->toHaveCount(1);
});

it('migrates the guest cart to the exact UUID user key on login', function (): void {
    $storage = new DatabaseStorage(app('db')->connection(), 'carts');
    $guestSessionId = session()->getId();

    $storage->putItems($guestSessionId, 'default', [
        'product-1' => [
            'id' => 'product-1',
            'name' => 'Guest UUID Product',
            'price' => 1000,
            'quantity' => 1,
            'attributes' => [],
        ],
    ], CartRevision::none());

    (new HandleUserLoginAttempt)->handle(
        new Attempting('web', ['email' => 'uuid-user@example.com', 'password' => 'secret'], false)
    );

    $user = new class
    {
        public $id = '019a1234-5b6c-7d8e-9f01-23456789abcd';

        public $email = 'uuid-user@example.com';
    };

    app(HandleUserLogin::class)->handle(new Login('web', $user, false));

    expect($storage->getItems('019a1234-5b6c-7d8e-9f01-23456789abcd', 'default'))->toHaveCount(1)
        ->and($storage->getItems('019', 'default'))->toBeEmpty()
        ->and($storage->getItems('19', 'default'))->toBeEmpty()
        ->and($storage->getItems($guestSessionId, 'default'))->toBeEmpty();
});
