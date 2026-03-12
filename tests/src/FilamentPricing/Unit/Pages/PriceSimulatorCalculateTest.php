<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\FilamentPricing\Pages\PriceSimulator;
use AIArmada\Pricing\Contracts\PriceCalculatorInterface;
use AIArmada\Pricing\Data\PriceResultData;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Carbon\CarbonImmutable;

uses(TestCase::class);

it('calculates pricing for a product using the bound PriceCalculator', function (): void {
    $product = Product::factory()->create(['price' => 1000]);

    $effectiveAt = CarbonImmutable::parse('2025-01-01 12:00:00');

    $capturedContext = null;

    $calculator = Mockery::mock(PriceCalculatorInterface::class)
        ->shouldIgnoreMissing();
    $calculator->shouldReceive('calculate')
        ->once()
        ->andReturnUsing(function ($item, $quantity, $context) use (&$capturedContext): PriceResultData {
            $capturedContext = $context;

            return new PriceResultData(
                originalPrice: 1000,
                finalPrice: 900,
                discountAmount: 100,
                discountSource: 'Promotion',
                discountPercentage: 10.0,
                priceListName: null,
                tierDescription: null,
                promotionName: 'Promo',
                breakdown: [
                    ['step' => 'Base', 'value' => 1000],
                    ['step' => 'Promotion', 'value' => 900],
                ],
            );
        });

    app()->instance(PriceCalculatorInterface::class, $calculator);

    $page = app(PriceSimulator::class);

    $page->data = [
        'product_type' => 'product',
        'product_id' => $product->getKey(),
        'customer_id' => null,
        'quantity' => 2,
        'effective_date' => $effectiveAt,
    ];

    $page->calculate();

    expect($capturedContext)->toHaveKey('effective_at')
        ->and($capturedContext['effective_at'])->toBeInstanceOf(DateTimeInterface::class);

    expect($page->result)->not->toBeNull();
    expect($page->result['final_price'])->toBe(900);
    expect($page->result['quantity'])->toBe(2);
    expect($page->result['total_price'])->toBe(1800);
});

it('passes customer_id in context when a customer is provided', function (): void {
    $product = Product::factory()->create(['price' => 1000]);

    $effectiveAt = CarbonImmutable::parse('2025-01-02 12:00:00');
    $customer = Customer::query()->create([
        'first_name' => 'Test',
        'last_name' => 'Customer',
        'email' => 'customer-' . uniqid() . '@example.com',
        'status' => CustomerStatus::Active,
        'wallet_balance' => 0,
        'lifetime_value' => 0,
        'total_orders' => 0,
        'accepts_marketing' => false,
        'last_order_at' => null,
    ]);

    $capturedContext = null;

    $calculator = Mockery::mock(PriceCalculatorInterface::class)
        ->shouldIgnoreMissing();
    $calculator->shouldReceive('calculate')
        ->once()
        ->andReturnUsing(function ($item, $quantity, $context) use (&$capturedContext): PriceResultData {
            $capturedContext = $context;

            return new PriceResultData(
                originalPrice: 1000,
                finalPrice: 1000,
                discountAmount: 0,
                discountSource: null,
                discountPercentage: null,
                priceListName: null,
                tierDescription: null,
                promotionName: null,
                breakdown: [],
            );
        });

    app()->instance(PriceCalculatorInterface::class, $calculator);

    $page = app(PriceSimulator::class);

    $page->data = [
        'product_type' => 'product',
        'product_id' => $product->getKey(),
        'customer_id' => $customer->getKey(),
        'quantity' => 1,
        'effective_date' => $effectiveAt,
    ];

    $page->calculate();

    expect($capturedContext)->toMatchArray(['customer_id' => $customer->getKey()])
        ->and($capturedContext)->toHaveKey('effective_at');
    expect($page->result)->not->toBeNull();
});

it('returns null result when the priceable cannot be resolved', function (): void {
    $page = app(PriceSimulator::class);

    $page->data = [
        'product_type' => 'product',
        'product_id' => 'does-not-exist',
        'customer_id' => null,
        'quantity' => 1,
        'effective_date' => now(),
    ];

    $page->calculate();

    expect($page->result)->toBeNull();
});

it('calculates pricing for a variant', function (): void {
    $product = Product::factory()->create(['price' => 1500]);

    $variant = Variant::query()->create([
        'product_id' => $product->getKey(),
        'sku' => 'SKU-' . uniqid(),
        'price' => 1500,
        'compare_price' => null,
        'cost' => null,
        'barcode' => null,
        'weight' => null,
        'length' => null,
        'width' => null,
        'height' => null,
        'is_default' => false,
        'is_enabled' => true,
        'metadata' => null,
    ]);

    $calculator = Mockery::mock(PriceCalculatorInterface::class)
        ->shouldIgnoreMissing();
    $calculator->shouldReceive('calculate')
        ->once()
        ->andReturn(new PriceResultData(
            originalPrice: 1500,
            finalPrice: 1500,
            discountAmount: 0,
            discountSource: null,
            discountPercentage: null,
            priceListName: null,
            tierDescription: null,
            promotionName: null,
            breakdown: [],
        ));

    app()->instance(PriceCalculatorInterface::class, $calculator);

    $page = app(PriceSimulator::class);

    $page->data = [
        'product_type' => 'variant',
        'variant_id' => $variant->getKey(),
        'customer_id' => null,
        'quantity' => 1,
        'effective_date' => now(),
    ];

    $page->calculate();

    expect($page->result)->not->toBeNull();
    expect($page->result['original_price'])->toBe(1500);
});

it('clears the result and re-fills the form', function (): void {
    $page = app(PriceSimulator::class);

    $page->result = ['final_price' => 123];

    $page->clear();

    expect($page->result)->toBeNull();
});
