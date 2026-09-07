<?php

declare(strict_types=1);

use AIArmada\CashierChip\Billing\CheckoutBuilder;
use AIArmada\Commerce\Tests\CashierChip\CashierChipTestCase;

uses(CashierChipTestCase::class);

describe('CheckoutBuilder', function (): void {
    it('can create guest builder', function (): void {
        $builder = new CheckoutBuilder;

        $this->assertInstanceOf(CheckoutBuilder::class, $builder);
    });

    it('can create builder with owner', function (): void {
        $user = $this->createUser(['chip_id' => 'cli_123']);
        $builder = new CheckoutBuilder($user);

        $this->assertInstanceOf(CheckoutBuilder::class, $builder);
    });

    it('recurring', function (): void {
        $builder = new CheckoutBuilder;

        $result = $builder->recurring();

        $this->assertSame($builder, $result);
    });

    it('recurring with false', function (): void {
        $builder = new CheckoutBuilder;
        $builder->recurring();

        $result = $builder->recurring(false);

        $this->assertSame($builder, $result);
    });

    it('success url', function (): void {
        $builder = new CheckoutBuilder;

        $result = $builder->successUrl('https://example.com/success');

        $this->assertSame($builder, $result);
    });

    it('cancel url', function (): void {
        $builder = new CheckoutBuilder;

        $result = $builder->cancelUrl('https://example.com/cancel');

        $this->assertSame($builder, $result);
    });

    it('webhook url', function (): void {
        $builder = new CheckoutBuilder;

        $result = $builder->webhookUrl('https://example.com/webhook');

        $this->assertSame($builder, $result);
    });

    it('with metadata', function (): void {
        $builder = new CheckoutBuilder;

        $result = $builder->withMetadata(['key' => 'value']);

        $this->assertSame($builder, $result);
    });

    it('add product', function (): void {
        $builder = new CheckoutBuilder;

        $result = $builder->addProduct('Test Product', 1000);

        $this->assertSame($builder, $result);
    });

    it('add product with quantity', function (): void {
        $builder = new CheckoutBuilder;

        $result = $builder->addProduct('Test Product', 1000, 5);

        $this->assertSame($builder, $result);
    });

    it('products', function (): void {
        $builder = new CheckoutBuilder;

        $result = $builder->products([
            ['name' => 'Product 1', 'price' => 1000, 'quantity' => 1],
        ]);

        $this->assertSame($builder, $result);
    });

    it('currency', function (): void {
        $builder = new CheckoutBuilder;

        $result = $builder->currency('MYR');

        $this->assertSame($builder, $result);
    });

    it('fluent chaining', function (): void {
        $builder = new CheckoutBuilder;

        $result = $builder
            ->recurring()
            ->successUrl('https://example.com/success')
            ->cancelUrl('https://example.com/cancel')
            ->webhookUrl('https://example.com/webhook')
            ->withMetadata(['key' => 'value'])
            ->addProduct('Test', 1000)
            ->currency('MYR');

        $this->assertInstanceOf(CheckoutBuilder::class, $result);
    });

    it('create keeps prices in cents', function (): void {
        $checkout = (new CheckoutBuilder)
            ->addProduct('Test Product', 1000, 2)
            ->create(2000);

        $payload = $checkout->toArray();

        $this->assertSame(1000, $payload['purchase']['products'][0]['price']);
        $this->assertSame('2', $payload['purchase']['products'][0]['quantity']);
        $this->assertSame(2000, $payload['purchase']['total']);
    });
});
