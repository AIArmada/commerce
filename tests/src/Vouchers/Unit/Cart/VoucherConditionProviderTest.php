<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Data\VoucherType;
use AIArmada\Vouchers\Data\VoucherValidationResult;
use AIArmada\Vouchers\Features\CartIntegration\Api\VoucherValidation;
use AIArmada\Vouchers\Features\CartIntegration\Extensions\VoucherConditionProvider;
use Mockery;

beforeEach(function (): void {
    $this->validator = Mockery::mock(VoucherValidation::class);
    $this->provider = new VoucherConditionProvider($this->validator);
});

afterEach(function (): void {
    Mockery::close();
});

/**
 * Creates a cart for testing.
 */
function createCartForVoucherConditionTest(array $metadata = []): Cart
{
    $cart = new Cart(new InMemoryStorage, 'voucher-condition-test');
    foreach ($metadata as $key => $value) {
        $cart->setMetadata($key, $value);
    }

    return $cart;
}

/**
 * Creates a VoucherData object for testing.
 *
 * Note: For percentages, value is in basis points (1000 = 10%, 1250 = 12.5%).
 * For fixed amounts, value is in cents (1000 = $10.00).
 */
function createVoucherData(
    string $id = 'voucher-123',
    string $code = 'SAVE10',
    VoucherType $type = VoucherType::Percentage,
    int $value = 1000,
    ?string $description = 'Save 10%',
    ?int $minCartValue = null,
    ?int $maxDiscount = null,
    ?string $currency = 'USD'
): VoucherData {
    return VoucherData::fromArray([
        'id' => $id,
        'code' => $code,
        'name' => $description ?? $code,
        'type' => $type,
        'value' => $value,
        'description' => $description,
        'min_cart_value' => $minCartValue,
        'max_discount' => $maxDiscount,
        'currency' => $currency,
        'status' => 'active',
    ]);
}

/**
 * Creates a valid VoucherValidationResult.
 */
function createValidValidationResult(?VoucherData $voucher = null): VoucherValidationResult
{
    return VoucherValidationResult::valid($voucher === null ? [] : ['voucher' => $voucher->toArray()]);
}

/**
 * Creates an invalid VoucherValidationResult.
 */
function createInvalidValidationResult(string $reason = 'Voucher is invalid'): VoucherValidationResult
{
    return VoucherValidationResult::invalid($reason);
}

describe('VoucherConditionProvider getType', function (): void {
    it('returns voucher type', function (): void {
        expect($this->provider->getType())->toBe('voucher');
    });
});

describe('VoucherConditionProvider getPriority', function (): void {
    it('returns priority 100', function (): void {
        expect($this->provider->getPriority())->toBe(100);
    });
});

describe('VoucherConditionProvider getConditionsFor', function (): void {
    it('returns empty array when no voucher codes in metadata', function (): void {
        $cart = createCartForVoucherConditionTest();

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('returns empty array when voucher codes array is empty', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => []]);

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('skips voucher when not found in service', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['INVALID']]);

        $this->validator->shouldReceive('validate')
            ->with('INVALID', $cart)
            ->once()
            ->andReturn(createInvalidValidationResult('Voucher not found.'));

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('skips voucher when validation fails', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['EXPIRED']]);
        $this->validator->shouldReceive('validate')
            ->with('EXPIRED', $cart)
            ->once()
            ->andReturn(createInvalidValidationResult('Voucher has expired'));

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toBeEmpty();
    });

    it('creates condition for valid percentage voucher', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['SAVE10']]);
        $voucher = createVoucherData(
            code: 'SAVE10',
            type: VoucherType::Percentage,
            value: 1000, // 10% = 1000 basis points
        );

        $this->validator->shouldReceive('validate')
            ->with('SAVE10', $cart)
            ->once()
            ->andReturn(createValidValidationResult($voucher));

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        expect($conditions[0])->toBeInstanceOf(CartCondition::class);
        expect($conditions[0]->getName())->toBe('voucher_SAVE10');
        expect($conditions[0]->getType())->toBe('voucher');
        expect($conditions[0]->getValue())->toBe('-10%');
    });

    it('creates condition for valid fixed voucher', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['FIXED20']]);
        $voucher = createVoucherData(
            code: 'FIXED20',
            type: VoucherType::Fixed,
            value: 2000, // $20.00 = 2000 cents
        );

        $this->validator->shouldReceive('validate')
            ->with('FIXED20', $cart)
            ->once()
            ->andReturn(createValidValidationResult($voucher));

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        expect($conditions[0]->getValue())->toBe('-2000');
    });

    it('creates condition for free shipping voucher', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['FREESHIP']]);
        $voucher = createVoucherData(
            code: 'FREESHIP',
            type: VoucherType::FreeShipping,
            value: 0,
        );

        $this->validator->shouldReceive('validate')
            ->with('FREESHIP', $cart)
            ->once()
            ->andReturn(createValidValidationResult($voucher));

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        expect($conditions[0]->getValue())->toBe('-100%');
    });

    it('handles multiple voucher codes', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['SAVE10', 'FIXED5']]);
        $voucher1 = createVoucherData(code: 'SAVE10', type: VoucherType::Percentage, value: 1000); // 10%
        $voucher2 = createVoucherData(code: 'FIXED5', type: VoucherType::Fixed, value: 500); // $5.00

        $this->validator->shouldReceive('validate')
            ->with('SAVE10', $cart)
            ->once()
            ->andReturn(createValidValidationResult($voucher1));

        $this->validator->shouldReceive('validate')
            ->with('FIXED5', $cart)
            ->once()
            ->andReturn(createValidValidationResult($voucher2));

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(2);
    });

    it('includes voucher attributes in condition', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['TEST']]);
        $voucher = createVoucherData(
            id: 'voucher-abc',
            code: 'TEST',
            type: VoucherType::Percentage,
            value: 1500, // 15% = 1500 basis points
            description: 'Test discount',
            minCartValue: 5000, // $50.00 = 5000 cents
            maxDiscount: 10000, // $100.00 = 10000 cents
            currency: 'EUR'
        );

        $this->validator->shouldReceive('validate')
            ->with('TEST', $cart)
            ->once()
            ->andReturn(createValidValidationResult($voucher));

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        $attributes = $conditions[0]->getAttributes();
        expect($attributes['voucher_id'])->toBe('voucher-abc');
        expect($attributes['voucher_code'])->toBe('TEST');
        expect($attributes['voucher_type'])->toBe('percentage');
        expect($attributes['description'])->toBe('Test discount');
        expect($attributes['min_cart_value'])->toBe(5000);
        expect($attributes['max_discount'])->toBe(10000);
        expect($attributes['currency'])->toBe('EUR');
    });

    it('sets correct target for percentage voucher', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['PERCENT']]);
        $voucher = createVoucherData(code: 'PERCENT', type: VoucherType::Percentage, value: 1000); // 10%

        $this->validator->shouldReceive('validate')
            ->with('PERCENT', $cart)
            ->once()
            ->andReturn(createValidValidationResult($voucher));

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        $targetArray = $conditions[0]->getTargetDefinition()->toArray();
        expect($targetArray['scope'])->toBe(ConditionScope::CART->value);
        expect($targetArray['phase'])->toBe(ConditionPhase::CART_SUBTOTAL->value);
        expect($targetArray['application'])->toBe(ConditionApplication::AGGREGATE->value);
    });

    it('sets correct target for free shipping voucher', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['SHIP']]);
        $voucher = createVoucherData(code: 'SHIP', type: VoucherType::FreeShipping, value: 0);

        $this->validator->shouldReceive('validate')
            ->with('SHIP', $cart)
            ->once()
            ->andReturn(createValidValidationResult($voucher));

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        $targetArray = $conditions[0]->getTargetDefinition()->toArray();
        expect($targetArray['phase'])->toBe(ConditionPhase::SHIPPING->value);
    });
});

describe('VoucherConditionProvider validate', function (): void {
    it('returns true for non-voucher condition types', function (): void {
        $cart = createCartForVoucherConditionTest();
        $condition = new CartCondition(
            name: 'OTHER',
            type: 'tax',
            target: [
                'scope' => ConditionScope::CART->value,
                'phase' => ConditionPhase::CART_SUBTOTAL->value,
                'application' => ConditionApplication::AGGREGATE->value,
            ],
            value: '10%'
        );

        $result = $this->provider->validate($condition, $cart);

        expect($result)->toBeTrue();
    });

    it('returns true for valid voucher condition', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['VALID']]);
        $condition = new CartCondition(
            name: 'voucher_VALID',
            type: 'voucher',
            target: [
                'scope' => ConditionScope::CART->value,
                'phase' => ConditionPhase::CART_SUBTOTAL->value,
                'application' => ConditionApplication::AGGREGATE->value,
            ],
            attributes: ['voucher_code' => 'VALID', 'voucher_id' => 'voucher-123', 'voucher_data' => createVoucherData(code: 'VALID')->toArray()],
            value: '-10%'
        );

        $this->validator->shouldReceive('validate')
            ->with('VALID', $cart)
            ->once()
            ->andReturn(createValidValidationResult(createVoucherData(code: 'VALID')));

        $result = $this->provider->validate($condition, $cart);

        expect($result)->toBeTrue();
    });

    it('returns false for invalid voucher condition', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['EXPIRED']]);
        $condition = new CartCondition(
            name: 'voucher_EXPIRED',
            type: 'voucher',
            target: [
                'scope' => ConditionScope::CART->value,
                'phase' => ConditionPhase::CART_SUBTOTAL->value,
                'application' => ConditionApplication::AGGREGATE->value,
            ],
            attributes: ['voucher_code' => 'EXPIRED', 'voucher_id' => 'voucher-123'],
            value: '-10%'
        );

        $this->validator->shouldReceive('validate')
            ->with('EXPIRED', $cart)
            ->once()
            ->andReturn(createInvalidValidationResult('Voucher expired'));

        $result = $this->provider->validate($condition, $cart);

        expect($result)->toBeFalse();
    });
});

describe('VoucherConditionProvider edge cases', function (): void {
    it('processes mixed valid and invalid vouchers', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['VALID', 'INVALID', 'NOTFOUND']]);

        $validVoucher = createVoucherData(code: 'VALID', type: VoucherType::Percentage, value: 1000); // 10%

        $this->validator->shouldReceive('validate')
            ->with('NOTFOUND', $cart)
            ->once()
            ->andReturn(createInvalidValidationResult('Voucher not found.'));

        $this->validator->shouldReceive('validate')
            ->with('VALID', $cart)
            ->once()
            ->andReturn(createValidValidationResult($validVoucher));

        $this->validator->shouldReceive('validate')
            ->with('INVALID', $cart)
            ->once()
            ->andReturn(createInvalidValidationResult());

        $conditions = $this->provider->getConditionsFor($cart);

        // Only the valid voucher should create a condition
        expect($conditions)->toHaveCount(1);
        expect($conditions[0]->getName())->toBe('voucher_VALID');
    });

    it('handles fixed voucher with decimal value', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['DECIMAL']]);
        $voucher = createVoucherData(
            code: 'DECIMAL',
            type: VoucherType::Fixed,
            value: 2550, // $25.50 = 2550 cents
        );

        $this->validator->shouldReceive('validate')
            ->with('DECIMAL', $cart)
            ->once()
            ->andReturn(createValidValidationResult($voucher));

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        // Fixed values are stored as cents, output as raw integer
        expect($conditions[0]->getValue())->toBe('-2550');
    });

    it('handles percentage voucher with decimal percentage', function (): void {
        $cart = createCartForVoucherConditionTest(['voucher_codes' => ['PERCENT']]);
        $voucher = createVoucherData(
            code: 'PERCENT',
            type: VoucherType::Percentage,
            value: 1250, // 12.5% = 1250 basis points
        );

        $this->validator->shouldReceive('validate')
            ->with('PERCENT', $cart)
            ->once()
            ->andReturn(createValidValidationResult($voucher));

        $conditions = $this->provider->getConditionsFor($cart);

        expect($conditions)->toHaveCount(1);
        expect($conditions[0]->getValue())->toBe('-12.5%');
    });
});

it('does not recreate a removed metadata voucher or duplicate normalized codes', function (): void {
    $cart = createCartForVoucherConditionTest(['voucher_codes' => ['SAVE10', ' save10 ']]);
    $voucher = createVoucherData();
    $this->validator->shouldReceive('validate')->with('SAVE10', $cart)->once()->andReturn(createValidValidationResult($voucher));

    $conditions = $this->provider->getConditionsFor($cart);
    expect($conditions)->toHaveCount(1);

    $cart->setMetadata('voucher_codes', []);
    expect($this->provider->validate($conditions[0], $cart))->toBeFalse();
});

it('rejects a provider condition when the same code resolves to another voucher', function (): void {
    $cart = createCartForVoucherConditionTest(['voucher_codes' => ['SAVE10']]);
    $selected = createVoucherData(id: 'selected');
    $replacement = createVoucherData(id: 'replacement');

    $this->validator->shouldReceive('validate')->with('SAVE10', $cart)->twice()->andReturn(
        createValidValidationResult($selected),
        createValidValidationResult($replacement),
    );

    $condition = $this->provider->getConditionsFor($cart)[0];

    expect($this->provider->validate($condition, $cart))->toBeFalse();
});

it('rejects provider conditions without voucher identity before validation', function (): void {
    $cart = createCartForVoucherConditionTest(['voucher_codes' => ['SAVE10']]);
    $condition = new CartCondition(
        name: 'voucher_SAVE10',
        type: 'voucher',
        target: [
            'scope' => ConditionScope::CART->value,
            'phase' => ConditionPhase::CART_SUBTOTAL->value,
            'application' => ConditionApplication::AGGREGATE->value,
        ],
        attributes: ['voucher_code' => 'SAVE10'],
        value: '-10%',
    );

    $this->validator->shouldNotReceive('validate');

    expect($this->provider->validate($condition, $cart))->toBeFalse();
});

it('rejects a provider condition when discount terms change for the same voucher', function (): void {
    $cart = createCartForVoucherConditionTest(['voucher_codes' => ['SAVE10']]);
    $selected = createVoucherData(value: 1000);
    $updated = createVoucherData(value: 500);

    $this->validator->shouldReceive('validate')->with('SAVE10', $cart)->twice()->andReturn(
        createValidValidationResult($selected),
        createValidValidationResult($updated),
    );

    $condition = $this->provider->getConditionsFor($cart)[0];

    expect($this->provider->validate($condition, $cart))->toBeFalse();
});
