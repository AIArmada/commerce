<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;

it('converts voucher condition to cart condition and back', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 1,
        'code' => 'TEST10',
        'name' => 'Test Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $voucherCondition = new VoucherCondition($voucherData, order: 75, dynamic: false);

    $cartCondition = $voucherCondition->toCartCondition();

    expect($cartCondition)->toBeInstanceOf(CartCondition::class)
        ->and($cartCondition->getType())->toBe('voucher')
        ->and($cartCondition->getAttributes()['voucher_code'] ?? null)->toBe('TEST10')
        ->and($cartCondition->getAttributes()['voucher_data']['code'] ?? null)->toBe('TEST10');

    $rehydrated = VoucherCondition::fromCartCondition($cartCondition);

    expect($rehydrated)->toBeInstanceOf(VoucherCondition::class);

    /** @var VoucherCondition $rehydratedVoucher */
    $rehydratedVoucher = $rehydrated;

    expect($rehydratedVoucher->getVoucherCode())->toBe('TEST10')
        ->and($rehydratedVoucher->getOrder())->toBe($cartCondition->getOrder());
});

it('returns null when fromCartCondition with non-voucher type', function (): void {
    $cartCondition = new CartCondition(
        name: 'test',
        type: 'discount',
        target: 'subtotal',
        value: '-10',
        attributes: [],
        order: 1
    );

    expect(VoucherCondition::fromCartCondition($cartCondition))->toBeNull();
});

it('returns null when fromCartCondition with invalid voucher data', function (): void {
    $cartCondition = new CartCondition(
        name: 'test',
        type: 'voucher',
        target: 'subtotal',
        value: '-10',
        attributes: ['voucher_data' => 'invalid'],
        order: 1
    );

    expect(VoucherCondition::fromCartCondition($cartCondition))->toBeNull();
});

it('applies percentage discount correctly', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 1,
        'code' => 'TEST10',
        'name' => 'Test Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $voucherCondition = new VoucherCondition($voucherData);

    expect($voucherCondition->apply(100.0))->toBe(90.0);
});

it('applies fixed discount correctly', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 1,
        'code' => 'TEST20',
        'name' => 'Test Voucher',
        'type' => VoucherType::Fixed->value,
        'value' => 20,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $voucherCondition = new VoucherCondition($voucherData);

    expect($voucherCondition->apply(100.0))->toBe(80.0);
});

it('applies max discount cap', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 1,
        'code' => 'TEST50',
        'name' => 'Test Voucher',
        'type' => VoucherType::Percentage->value,
        'value' => 50,
        'max_discount' => 25,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $voucherCondition = new VoucherCondition($voucherData);

    expect($voucherCondition->apply(100.0))->toBe(75.0); // 50% of 100 is 50, but capped at 25
});

it('handles free shipping voucher', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 1,
        'code' => 'FREESHIP',
        'name' => 'Free Shipping',
        'type' => VoucherType::FreeShipping->value,
        'value' => 0,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $voucherCondition = new VoucherCondition($voucherData);

    expect($voucherCondition->isFreeShipping())->toBeTrue()
        ->and($voucherCondition->apply(50.0))->toBe(50.0); // No change for shipping
});

it('gets calculated value', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 1,
        'code' => 'TEST10',
        'name' => 'Test Voucher',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $voucherCondition = new VoucherCondition($voucherData);

    expect($voucherCondition->getCalculatedValue(100.0))->toBe(-10.0);
});

it('checks discount type', function (): void {
    $discountVoucher = VoucherData::fromArray([
        'id' => '1',
        'code' => 'DISCOUNT',
        'name' => 'Discount',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $condition = new VoucherCondition($discountVoucher);

    expect($condition->isDiscount())->toBeTrue()
        ->and($condition->isCharge())->toBeFalse();
});

it('checks percentage type', function (): void {
    $percentageVoucher = VoucherData::fromArray([
        'id' => 1,
        'code' => 'PERCENT',
        'name' => 'Percent',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $fixedVoucher = VoucherData::fromArray([
        'id' => 2,
        'code' => 'FIXED',
        'name' => 'Fixed',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $percentCondition = new VoucherCondition($percentageVoucher);
    $fixedCondition = new VoucherCondition($fixedVoucher);

    expect($percentCondition->isPercentage())->toBeTrue()
        ->and($fixedCondition->isPercentage())->toBeFalse();
});

it('checks dynamic condition', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 1,
        'code' => 'TEST',
        'name' => 'Test',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $dynamicCondition = new VoucherCondition($voucherData, dynamic: true);
    $staticCondition = new VoucherCondition($voucherData, dynamic: false);

    expect($dynamicCondition->isDynamic())->toBeTrue()
        ->and($staticCondition->isDynamic())->toBeFalse();
});

it('gets condition properties', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 1,
        'code' => 'TEST',
        'name' => 'Test Voucher',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $condition = new VoucherCondition($voucherData, order: 5);

    expect($condition->getName())->toBe('voucher_TEST')
        ->and($condition->getType())->toBe('voucher')
        ->and($condition->getTarget())->toBe('subtotal')
        ->and($condition->getValue())->toBe('-10')
        ->and($condition->getOrder())->toBe(5)
        ->and($condition->getVoucherId())->toBe('1')
        ->and($condition->getVoucher())->toBe($voucherData);
});

it('gets attributes and rule context', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 1,
        'code' => 'TEST',
        'name' => 'Test Voucher',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
        'description' => 'Test description',
    ]);

    $condition = new VoucherCondition($voucherData);

    $attributes = $condition->getAttributes();
    expect($attributes['voucher_code'])->toBe('TEST')
        ->and($attributes['voucher_id'])->toBe('1')
        ->and($attributes['description'])->toBe('Test description');

    $ruleContext = $condition->getRuleFactoryContext();
    expect($ruleContext['voucher_code'])->toBe('TEST')
        ->and($ruleContext['voucher_id'])->toBe('1');
});

it('converts to array', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => 1,
        'code' => 'TEST',
        'name' => 'Test Voucher',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
        'description' => 'Test',
        'max_discount' => 50,
    ]);

    $condition = new VoucherCondition($voucherData, order: 10);

    $array = $condition->toArray();

    expect($array['name'])->toBe('voucher_TEST')
        ->and($array['type'])->toBe('voucher')
        ->and($array['target'])->toBe('subtotal')
        ->and($array['value'])->toBe('-10')
        ->and($array['order'])->toBe(10)
        ->and($array['is_discount'])->toBeTrue()
        ->and($array['is_percentage'])->toBeFalse()
        ->and($array['voucher']['code'])->toBe('TEST')
        ->and($array['voucher']['max_discount_amount'])->toBe(50.0);
});

it('converts to json', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => '1',
        'code' => 'TEST',
        'name' => 'Test Voucher',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $condition = new VoucherCondition($voucherData);

    $json = $condition->toJson();
    expect($json)->toBeJson();
});

it('caches cart condition on second call', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => '1',
        'code' => 'CACHE',
        'name' => 'Cache Test',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $condition = new VoucherCondition($voucherData);

    $first = $condition->toCartCondition();
    $second = $condition->toCartCondition();

    expect($first)->toBe($second); // Same instance
});

it('gets rules for dynamic condition', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => '1',
        'code' => 'RULES',
        'name' => 'Rules Test',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $dynamicCondition = new VoucherCondition($voucherData, dynamic: true);
    $staticCondition = new VoucherCondition($voucherData, dynamic: false);

    expect($dynamicCondition->getRules())->toBeArray()
        ->and($staticCondition->getRules())->toBeNull();
});

it('applies condition with different operators', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => '1',
        'code' => 'OPERATOR',
        'name' => 'Operator Test',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $condition = new VoucherCondition($voucherData);

    // Test private applyCondition via reflection
    $reflection = new ReflectionClass($condition);
    $method = $reflection->getMethod('applyCondition');
    $method->setAccessible(true);

    expect($method->invoke($condition, 100.0))->toBe(90.0); // -10

    // Test with percentage
    $percentData = VoucherData::fromArray([
        'id' => '2',
        'code' => 'PERCENT',
        'name' => 'Percent Test',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $percentCondition = new VoucherCondition($percentData);
    expect($method->invoke($percentCondition, 100.0))->toBe(90.0); // 100 * 0.9
});

it('gets operator from value', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => '1',
        'code' => 'OP',
        'name' => 'Op Test',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $condition = new VoucherCondition($voucherData);

    // Test private getOperator
    $reflection = new ReflectionClass($condition);
    $method = $reflection->getMethod('getOperator');
    $method->setAccessible(true);

    expect($method->invoke($condition))->toBe('-'); // For -10

    // Test percentage
    $percentData = VoucherData::fromArray([
        'id' => '2',
        'code' => 'PERC',
        'name' => 'Perc Test',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $percentCondition = new VoucherCondition($percentData);
    expect($method->invoke($percentCondition))->toBe('%');
});

it('parses percent value', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => '1',
        'code' => 'PARSE',
        'name' => 'Parse Test',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $condition = new VoucherCondition($voucherData);

    // Test private parsePercentValue
    $reflection = new ReflectionClass($condition);
    $method = $reflection->getMethod('parsePercentValue');
    $method->setAccessible(true);

    expect($method->invoke($condition, '20%'))->toBe(0.2);
});

it('applies percentage calculation', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => '1',
        'code' => 'APPLYPERC',
        'name' => 'Apply Perc Test',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $condition = new VoucherCondition($voucherData);

    // Test private applyPercentage
    $reflection = new ReflectionClass($condition);
    $method = $reflection->getMethod('applyPercentage');
    $method->setAccessible(true);

    expect($method->invoke($condition, 100.0, 0.1))->toBe(110.0); // 100 + 100*0.1
});

it('formats voucher value for different types', function (): void {
    // Test private formatVoucherValue
    $reflection = new ReflectionClass(VoucherCondition::class);
    $method = $reflection->getMethod('formatVoucherValue');
    $method->setAccessible(true);

    $fixedData = VoucherData::fromArray([
        'id' => '1',
        'code' => 'FIXED',
        'name' => 'Fixed',
        'type' => VoucherType::Fixed->value,
        'value' => 25,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $percentData = VoucherData::fromArray([
        'id' => '2',
        'code' => 'PERCENT',
        'name' => 'Percent',
        'type' => VoucherType::Percentage->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $freeShippingData = VoucherData::fromArray([
        'id' => '3',
        'code' => 'FREE',
        'name' => 'Free',
        'type' => VoucherType::FreeShipping->value,
        'value' => 0,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $condition = new VoucherCondition($fixedData);
    expect($method->invoke($condition, $fixedData))->toBe('-25');
    expect($method->invoke($condition, $percentData))->toBe('-10%');
    expect($method->invoke($condition, $freeShippingData))->toBe('+0');
});

it('determines target based on type', function (): void {
    // Test private determineTarget
    $reflection = new ReflectionClass(VoucherCondition::class);
    $method = $reflection->getMethod('determineTarget');
    $method->setAccessible(true);

    $fixedData = VoucherData::fromArray([
        'id' => '1',
        'code' => 'FIXED',
        'name' => 'Fixed',
        'type' => VoucherType::Fixed->value,
        'value' => 25,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $freeShippingData = VoucherData::fromArray([
        'id' => '2',
        'code' => 'FREE',
        'name' => 'Free',
        'type' => VoucherType::FreeShipping->value,
        'value' => 0,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $condition = new VoucherCondition($fixedData);
    expect($method->invoke($condition, $fixedData))->toBe('subtotal');
    expect($method->invoke($condition, $freeShippingData))->toBe('total');
});

it('gets attributes', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => '1',
        'code' => 'ATTR',
        'name' => 'Attr Test',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $condition = new VoucherCondition($voucherData);

    expect($condition->getAttribute('voucher_code'))->toBe('ATTR')
        ->and($condition->getAttribute('missing', 'default'))->toBe('default');
});

it('includes voucher data in toArray', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => '1',
        'code' => 'ARRAY',
        'name' => 'Array Test',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
        'description' => 'Test desc',
        'max_discount' => 50,
    ]);

    $condition = new VoucherCondition($voucherData);

    $array = $condition->toArray();

    expect($array['voucher']['id'])->toBe('1')
        ->and($array['voucher']['code'])->toBe('ARRAY')
        ->and($array['voucher']['max_discount_amount'])->toBe(50.0);
});

it('encodes to json', function (): void {
    $voucherData = VoucherData::fromArray([
        'id' => '1',
        'code' => 'JSON',
        'name' => 'Json Test',
        'type' => VoucherType::Fixed->value,
        'value' => 10,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    $condition = new VoucherCondition($voucherData);

    $json = $condition->toJson();
    $decoded = json_decode($json, true);

    expect($decoded)->toBeArray()
        ->and($decoded['name'])->toBe('voucher_JSON');
});
