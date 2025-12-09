<?php

declare(strict_types=1);

use AIArmada\CashierChip\CheckoutBuilder;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;

beforeEach(function (): void {
    $this->owner = new User([
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
});

it('can create checkout builder without owner', function (): void {
    $builder = new CheckoutBuilder;

    expect($builder)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can create checkout builder with owner', function (): void {
    $builder = new CheckoutBuilder($this->owner);

    expect($builder)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can set recurring', function (): void {
    $builder = new CheckoutBuilder($this->owner);

    $result = $builder->recurring();

    expect($result)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can set success url', function (): void {
    $builder = new CheckoutBuilder($this->owner);

    $result = $builder->successUrl('https://example.com/success');

    expect($result)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can set cancel url', function (): void {
    $builder = new CheckoutBuilder($this->owner);

    $result = $builder->cancelUrl('https://example.com/cancel');

    expect($result)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can set webhook url', function (): void {
    $builder = new CheckoutBuilder($this->owner);

    $result = $builder->webhookUrl('https://example.com/webhook');

    expect($result)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can add metadata', function (): void {
    $builder = new CheckoutBuilder($this->owner);

    $result = $builder->withMetadata(['order_id' => '12345']);

    expect($result)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can add product', function (): void {
    $builder = new CheckoutBuilder($this->owner);

    $result = $builder->addProduct('Test Product', 9900, 2);

    expect($result)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can set multiple products', function (): void {
    $builder = new CheckoutBuilder($this->owner);

    $products = [
        ['name' => 'Product 1', 'price' => 50.00, 'quantity' => 1],
        ['name' => 'Product 2', 'price' => 25.00, 'quantity' => 2],
    ];

    $result = $builder->products($products);

    expect($result)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can set currency', function (): void {
    $builder = new CheckoutBuilder($this->owner);

    $result = $builder->currency('USD');

    expect($result)->toBeInstanceOf(CheckoutBuilder::class);
});

it('can chain multiple methods', function (): void {
    $builder = new CheckoutBuilder($this->owner);

    $result = $builder
        ->recurring()
        ->successUrl('https://example.com/success')
        ->cancelUrl('https://example.com/cancel')
        ->webhookUrl('https://example.com/webhook')
        ->currency('MYR')
        ->withMetadata(['order_id' => '12345'])
        ->addProduct('Test Product', 9900);

    expect($result)->toBeInstanceOf(CheckoutBuilder::class);
});
