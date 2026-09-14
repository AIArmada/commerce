<?php

declare(strict_types=1);

use AIArmada\Orders\Actions\CreateOrder;

if (! function_exists('foldedValidOrderData')) {
    function foldedValidOrderData(array $overrides = []): array
    {
        return array_merge([
            'order_number' => 'ORD-FOLD-' . uniqid(),
            'currency' => 'MYR',
            'subtotal' => 5000,
            'grand_total' => 5000,
        ], $overrides);
    }
}

if (! function_exists('foldedValidItem')) {
    function foldedValidItem(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Widget',
            'quantity' => 1,
            'unit_price' => 5000,
        ], $overrides);
    }
}

it('rejects line discounts that exceed the folded discount total', function (): void {
    expect(fn () => app(CreateOrder::class)->execute(
        foldedValidOrderData(['discount_total' => 100]),
        [foldedValidItem(['discount_amount' => 500])],
    ))->toThrow(InvalidArgumentException::class, 'discount_total');
});

it('accepts line discounts folded into the discount total', function (): void {
    $order = app(CreateOrder::class)->execute(
        foldedValidOrderData(['discount_total' => 500]),
        [foldedValidItem(['discount_amount' => 500])],
    );

    expect($order->discount_total)->toBe(500)
        ->and($order->items->first()->discount_amount)->toBe(500);
});
