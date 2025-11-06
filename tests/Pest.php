<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)->in(
    'src/Cart',
    'src/Chip',
    'src/Docs',
    'src/FilamentCart',
    'src/FilamentChip',
    'src/Jnt',
    'src/Stock',
    'src/Vouchers',
);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeCartable', function () {
    return $this->toBeInstanceOf(AIArmada\Cart\Contracts\CartableInterface::class);
});

expect()->extend('toHaveValidCartStructure', function () {
    return $this->toHaveKeys(['items', 'conditions', 'metadata']);
});

/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
*/

function createSampleCartData(): array
{
    return [
        [
            'id' => 'test-product-1',
            'name' => 'Test Product 1',
            'price' => 99.99,
            'quantity' => 2,
            'attributes' => ['color' => 'red', 'size' => 'large'],
        ],
        [
            'id' => 'test-product-2',
            'name' => 'Test Product 2',
            'price' => 149.99,
            'quantity' => 1,
            'attributes' => ['brand' => 'TestBrand'],
        ],
    ];
}

function createSampleConditionData(): array
{
    return [
        'discount' => [
            'name' => 'Test Discount',
            'type' => 'discount',
            'target' => 'total',
            'value' => '-10%',
        ],
        'tax' => [
            'name' => 'Test Tax',
            'type' => 'tax',
            'target' => 'total',
            'value' => '+8.5%',
        ],
        'shipping' => [
            'name' => 'Test Shipping',
            'type' => 'shipping',
            'target' => 'total',
            'value' => '+15.00',
        ],
    ];
}
