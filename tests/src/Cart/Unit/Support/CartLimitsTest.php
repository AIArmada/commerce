<?php

declare(strict_types=1);

use AIArmada\Cart\Support\CartLimits;

it('reads all cart limits from the core config', function (): void {
    config()->set('cart.limits', [
        'max_items' => 25,
        'max_item_quantity' => 50,
        'max_data_size_bytes' => 4096,
        'max_string_length' => 120,
    ]);

    expect(CartLimits::fromConfig())
        ->maxItems->toBe(25)
        ->maxItemQuantity->toBe(50)
        ->maxDataSizeBytes->toBe(4096)
        ->maxStringLength->toBe(120);
});

it('clamps invalid configured limits to safe operational values', function (): void {
    config()->set('cart.limits', [
        'max_items' => -1,
        'max_item_quantity' => 0,
        'max_data_size_bytes' => 0,
        'max_string_length' => -20,
    ]);

    expect(CartLimits::fromConfig())
        ->maxItems->toBe(0)
        ->maxItemQuantity->toBe(1)
        ->maxDataSizeBytes->toBe(1)
        ->maxStringLength->toBe(1);
});
