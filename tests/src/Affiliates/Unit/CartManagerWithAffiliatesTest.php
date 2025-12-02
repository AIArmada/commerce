<?php

declare(strict_types=1);

use AIArmada\Affiliates\Support\CartManagerWithAffiliates;
use AIArmada\Cart\Contracts\CartManagerInterface;

test('CartManagerWithAffiliates fromCartManager returns same instance if already wrapped', function (): void {
    $manager = mock(CartManagerInterface::class);
    $wrapped = new CartManagerWithAffiliates($manager);

    $result = CartManagerWithAffiliates::fromCartManager($wrapped);

    expect($result)->toBe($wrapped);
});

test('CartManagerWithAffiliates fromCartManager wraps new manager', function (): void {
    $manager = mock(CartManagerInterface::class);

    $result = CartManagerWithAffiliates::fromCartManager($manager);

    expect($result)->toBeInstanceOf(CartManagerWithAffiliates::class);
});

test('CartManagerWithAffiliates getBaseManager unwraps decorators', function (): void {
    $base = mock(CartManagerInterface::class);
    $wrapped1 = new CartManagerWithAffiliates($base);
    $wrapped2 = new CartManagerWithAffiliates($wrapped1);

    $result = $wrapped2->getBaseManager();

    expect($result)->toBe($base);
});

test('CartManagerWithAffiliates delegates methods to manager', function (): void {
    $manager = mock(CartManagerInterface::class);
    $manager->shouldReceive('instance')->andReturn('test');

    $wrapped = new CartManagerWithAffiliates($manager);

    expect($wrapped->instance())->toBe('test');
});
