<?php

declare(strict_types=1);

use AIArmada\Cart\Support\LoginMigrationCacheKey;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;

it('partitions login migration cache keys by owner', function (): void {
    config()->set('cart.owner.enabled', true);

    $ownerA = User::query()->create([
        'name' => 'Cart Cache Owner A',
        'email' => 'cart-cache-a@example.test',
        'password' => 'secret',
    ]);
    $ownerB = User::query()->create([
        'name' => 'Cart Cache Owner B',
        'email' => 'cart-cache-b@example.test',
        'password' => 'secret',
    ]);

    $keyA = OwnerContext::withOwner($ownerA, fn (): string => LoginMigrationCacheKey::make('guest@example.test'));
    $keyB = OwnerContext::withOwner($ownerB, fn (): string => LoginMigrationCacheKey::make('guest@example.test'));

    expect($keyA)->not->toBe($keyB);
});
