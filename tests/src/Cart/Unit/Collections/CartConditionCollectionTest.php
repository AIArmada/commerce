<?php

declare(strict_types=1);

use AIArmada\Cart\Collections\CartConditionCollection;
use AIArmada\Cart\Conditions\CartCondition;

it('can add conditions to collection', function (): void {
    $collection = new CartConditionCollection;

    $condition = new CartCondition(
        name: 'Test Condition',
        type: 'discount',
        target: 'subtotal',
        value: '-10%'
    );

    $collection->addCondition($condition);

    expect($collection->count())->toBe(1);
    expect($collection->hasCondition('Test Condition'))->toBeTrue();
    expect($collection->getCondition('Test Condition'))->toBe($condition);
});

it('can filter conditions by type', function (): void {
    $collection = new CartConditionCollection;

    $discount = new CartCondition('Discount', 'discount', 'subtotal', '-10%');
    $tax = new CartCondition('Tax', 'tax', 'subtotal', '8%');
    $shipping = new CartCondition('Shipping', 'shipping', 'subtotal', '+15');

    $collection->addCondition($discount);
    $collection->addCondition($tax);
    $collection->addCondition($shipping);

    $discounts = $collection->byType('discount');
    expect($discounts->count())->toBe(1);
    expect($discounts->first())->toBe($discount);
});

it('can filter conditions by target', function (): void {
    $collection = new CartConditionCollection;

    $subtotalCondition = new CartCondition('Subtotal Tax', 'tax', 'subtotal', '8%');
    $totalCondition = new CartCondition('Total Fee', 'fee', 'total', '+5');

    $collection->addCondition($subtotalCondition);
    $collection->addCondition($totalCondition);

    $subtotalConditions = $collection->byTarget('subtotal');
    expect($subtotalConditions->count())->toBe(1);
    expect($subtotalConditions->first())->toBe($subtotalCondition);
});

it('can get only discount conditions', function (): void {
    $collection = new CartConditionCollection;

    $discount = new CartCondition('Discount', 'discount', 'subtotal', '-10%');
    $charge = new CartCondition('Charge', 'fee', 'subtotal', '+5');

    $collection->addCondition($discount);
    $collection->addCondition($charge);

    $discounts = $collection->discounts();
    expect($discounts->count())->toBe(1);
    expect($discounts->first())->toBe($discount);
});

it('can get only charge conditions', function (): void {
    $collection = new CartConditionCollection;

    $discount = new CartCondition('Discount', 'discount', 'subtotal', '-10%');
    $charge = new CartCondition('Charge', 'fee', 'subtotal', '+5');

    $collection->addCondition($discount);
    $collection->addCondition($charge);

    $charges = $collection->charges();
    expect($charges->count())->toBe(1);
    expect($charges->first())->toBe($charge);
});

it('can apply all conditions to a value', function (): void {
    $collection = new CartConditionCollection;

    // Create conditions with specific order
    $discount = new CartCondition('Discount', 'discount', 'subtotal', '-10%', [], 1);
    $tax = new CartCondition('Tax', 'tax', 'subtotal', '8%', [], 2);

    $collection->addCondition($discount);
    $collection->addCondition($tax);

    // Start with $100
    // Apply 10% discount: $100 * 0.9 = $90
    // Apply 8% tax: $90 * 1.08 = $97.20
    $result = $collection->applyAll(100.0);
    expect($result->getAmount())->toBe(97.2);
});

it('can create collection from array', function (): void {
    $conditions = [
        [
            'name' => 'Discount',
            'type' => 'discount',
            'target' => 'subtotal',
            'value' => '-10%',
        ],
        [
            'name' => 'Tax',
            'type' => 'tax',
            'target' => 'subtotal',
            'value' => '8%',
        ],
    ];

    $collection = CartConditionCollection::fromArray($conditions);

    expect($collection->count())->toBe(2);
    expect($collection->hasCondition('Discount'))->toBeTrue();
    expect($collection->hasCondition('Tax'))->toBeTrue();
});

it('can get collection summary', function (): void {
    $collection = new CartConditionCollection;

    $discount = new CartCondition('Discount', 'discount', 'subtotal', '-10%');
    $tax = new CartCondition('Tax', 'tax', 'subtotal', '8%');

    $collection->addCondition($discount);
    $collection->addCondition($tax);

    $summary = $collection->getSummary(100.0);

    expect($summary)->toHaveKeys([
        'total_conditions', 'discounts', 'charges', 'percentages',
        'total_discount_amount', 'total_charges_amount', 'net_adjustment',
    ]);

    expect($summary['total_conditions'])->toBe(2);
    expect($summary['discounts'])->toBe(1);
    expect($summary['charges'])->toBe(1);
    expect($summary['percentages'])->toBe(2);
});

it('can group conditions by type', function (): void {
    $collection = new CartConditionCollection;

    $discount1 = new CartCondition('Discount 1', 'discount', 'subtotal', '-10%');
    $discount2 = new CartCondition('Discount 2', 'discount', 'subtotal', '-5%');
    $tax = new CartCondition('Tax', 'tax', 'subtotal', '8%');

    $collection->addCondition($discount1);
    $collection->addCondition($discount2);
    $collection->addCondition($tax);

    $grouped = $collection->groupByType();

    expect($grouped->has('discount'))->toBeTrue();
    expect($grouped->has('tax'))->toBeTrue();
    expect($grouped->get('discount')->count())->toBe(2);
    expect($grouped->get('tax')->count())->toBe(1);
});

it('can remove conditions', function (): void {
    $collection = new CartConditionCollection;

    $condition = new CartCondition('Test', 'discount', 'subtotal', '-10%');
    $collection->addCondition($condition);

    expect($collection->hasCondition('Test'))->toBeTrue();

    $collection = $collection->removeCondition('Test');

    expect($collection->hasCondition('Test'))->toBeFalse();
});

it('can filter conditions by value', function (): void {
    $collection = new CartConditionCollection;

    $discount1 = new CartCondition('Discount 1', 'discount', 'subtotal', '-10%');
    $discount2 = new CartCondition('Discount 2', 'discount', 'subtotal', '-5%');
    $discount3 = new CartCondition('Discount 3', 'discount', 'subtotal', '-10%');

    $collection->addCondition($discount1);
    $collection->addCondition($discount2);
    $collection->addCondition($discount3);

    $tenPercent = $collection->byValue('-10%');

    expect($tenPercent->count())->toBe(2);
});

it('can filter percentage conditions', function (): void {
    $collection = new CartConditionCollection;

    $percent = new CartCondition('Percent', 'discount', 'subtotal', '-10%');
    $fixed = new CartCondition('Fixed', 'discount', 'subtotal', -5.0);

    $collection->addCondition($percent);
    $collection->addCondition($fixed);

    $percentages = $collection->percentages();

    expect($percentages->count())->toBe(1);
    expect($percentages->first()->getName())->toBe('Percent');
});

it('can sort conditions by order', function (): void {
    $collection = new CartConditionCollection;

    $condition1 = new CartCondition('First', 'discount', 'subtotal', '-10%', [], 3);
    $condition2 = new CartCondition('Second', 'tax', 'subtotal', '5%', [], 1);
    $condition3 = new CartCondition('Third', 'fee', 'subtotal', '+10', [], 2);

    $collection->addCondition($condition1);
    $collection->addCondition($condition2);
    $collection->addCondition($condition3);

    $sorted = $collection->sortByOrder();
    $values = $sorted->values();

    expect($values->first()->getName())->toBe('Second');
    expect($values->last()->getName())->toBe('First');
});

it('can get total discount amount', function (): void {
    $collection = new CartConditionCollection;

    $discount1 = new CartCondition('Discount 1', 'discount', 'subtotal', '-10%');
    $discount2 = new CartCondition('Discount 2', 'discount', 'subtotal', '-5');

    $collection->addCondition($discount1);
    $collection->addCondition($discount2);

    $totalDiscount = $collection->getTotalDiscount(100.0);

    expect($totalDiscount)->toBeGreaterThan(0);
});

it('can get total charges amount', function (): void {
    $collection = new CartConditionCollection;

    $charge1 = new CartCondition('Charge 1', 'fee', 'subtotal', '+5%');
    $charge2 = new CartCondition('Charge 2', 'tax', 'subtotal', '+10');

    $collection->addCondition($charge1);
    $collection->addCondition($charge2);

    $totalCharges = $collection->getTotalCharges(100.0);

    expect($totalCharges)->toBeGreaterThan(0);
});

it('can convert to detailed array', function (): void {
    $collection = new CartConditionCollection;

    $discount = new CartCondition('Discount', 'discount', 'subtotal', '-10%');
    $collection->addCondition($discount);

    $detailed = $collection->toDetailedArray(100.0);

    expect($detailed)->toBeArray();
    expect($detailed)->toHaveKeys(['conditions', 'summary']);
    expect($detailed['conditions'])->toBeArray();
    expect($detailed['summary'])->toBeArray();
});

it('can group conditions by target', function (): void {
    $collection = new CartConditionCollection;

    $subtotal1 = new CartCondition('Subtotal 1', 'discount', 'subtotal', '-10%');
    $subtotal2 = new CartCondition('Subtotal 2', 'tax', 'subtotal', '5%');
    $total1 = new CartCondition('Total 1', 'fee', 'total', '+10');

    $collection->addCondition($subtotal1);
    $collection->addCondition($subtotal2);
    $collection->addCondition($total1);

    $grouped = $collection->groupByTarget();

    expect($grouped->has('subtotal'))->toBeTrue();
    expect($grouped->has('total'))->toBeTrue();
    expect($grouped->get('subtotal')->count())->toBe(2);
    expect($grouped->get('total')->count())->toBe(1);
});

it('can check if collection has discounts', function (): void {
    $collection = new CartConditionCollection;

    expect($collection->hasDiscounts())->toBeFalse();

    $discount = new CartCondition('Discount', 'discount', 'subtotal', '-10%');
    $collection->addCondition($discount);

    expect($collection->hasDiscounts())->toBeTrue();
});

it('can check if collection has charges', function (): void {
    $collection = new CartConditionCollection;

    expect($collection->hasCharges())->toBeFalse();

    $charge = new CartCondition('Charge', 'fee', 'subtotal', '+10');
    $collection->addCondition($charge);

    expect($collection->hasCharges())->toBeTrue();
});

it('can filter conditions with specific attribute', function (): void {
    $collection = new CartConditionCollection;

    $condition1 = new CartCondition('Condition 1', 'discount', 'subtotal', '-10%', ['featured' => true]);
    $condition2 = new CartCondition('Condition 2', 'tax', 'subtotal', '5%', ['featured' => false]);
    $condition3 = new CartCondition('Condition 3', 'fee', 'subtotal', '+10');

    $collection->addCondition($condition1);
    $collection->addCondition($condition2);
    $collection->addCondition($condition3);

    $featured = $collection->withAttribute('featured', true);
    expect($featured->count())->toBe(1);

    $hasFeatured = $collection->withAttribute('featured');
    expect($hasFeatured->count())->toBe(2);
});

it('can find condition by attribute', function (): void {
    $collection = new CartConditionCollection;

    $condition1 = new CartCondition('Condition 1', 'discount', 'subtotal', '-10%', ['code' => 'SAVE10']);
    $condition2 = new CartCondition('Condition 2', 'tax', 'subtotal', '5%', ['code' => 'TAX5']);

    $collection->addCondition($condition1);
    $collection->addCondition($condition2);

    $found = $collection->findByAttribute('code', 'SAVE10');

    expect($found)->not->toBeNull();
    expect($found->getName())->toBe('Condition 1');
});

it('can remove conditions by type', function (): void {
    $collection = new CartConditionCollection;

    $discount = new CartCondition('Discount', 'discount', 'subtotal', '-10%');
    $tax = new CartCondition('Tax', 'tax', 'subtotal', '5%');
    $fee = new CartCondition('Fee', 'fee', 'subtotal', '+10');

    $collection->addCondition($discount);
    $collection->addCondition($tax);
    $collection->addCondition($fee);

    $filtered = $collection->removeByType('discount');

    expect($filtered->count())->toBe(2);
    expect($filtered->hasCondition('Discount'))->toBeFalse();
    expect($filtered->hasCondition('Tax'))->toBeTrue();
});

it('can remove conditions by target', function (): void {
    $collection = new CartConditionCollection;

    $subtotal = new CartCondition('Subtotal', 'discount', 'subtotal', '-10%');
    $total = new CartCondition('Total', 'tax', 'total', '5%');
    $item = new CartCondition('Item', 'fee', 'item', '+10');

    $collection->addCondition($subtotal);
    $collection->addCondition($total);
    $collection->addCondition($item);

    $filtered = $collection->removeByTarget('subtotal');

    expect($filtered->count())->toBe(2);
    expect($filtered->hasCondition('Subtotal'))->toBeFalse();
    expect($filtered->hasCondition('Total'))->toBeTrue();
});
