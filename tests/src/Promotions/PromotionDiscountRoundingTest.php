<?php

declare(strict_types=1);

use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;

it('caps percentage discounts at the price', function (): void {
    $promotion = new Promotion([
        'type' => PromotionType::Percentage,
        'discount_value' => 150,
    ]);

    expect($promotion->calculateDiscount(10000))->toBe(10000);
});

it('rounds percentage discounts with integer half-up math', function (): void {
    $promotion = new Promotion([
        'type' => PromotionType::Percentage,
        'discount_value' => 10,
    ]);

    // 10% of 5 minor units is exactly half a unit: rounds up to 1.
    expect($promotion->calculateDiscount(5))->toBe(1)
        ->and($promotion->calculateDiscount(999))->toBe(100);
});
