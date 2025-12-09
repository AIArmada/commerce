<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Compound\Conditions\CashbackVoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;

/**
 * Create a test cart with a specified total value.
 *
 * @param  int  $totalValue  Total cart value in cents
 * @param  int  $quantity  Number of items (for per-item cashback tests)
 */
function createCashbackTestCart(int $totalValue, int $quantity = 1): Cart
{
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-cashback', events: null);

    $pricePerItem = (int) ($totalValue / $quantity);

    $cart->add(
        id: 'PRODUCT-001',
        name: 'Product',
        price: $pricePerItem,
        quantity: $quantity,
        attributes: ['sku' => 'PRODUCT-001']
    );

    return $cart;
}

/**
 * Create a cashback voucher data object for testing.
 */
function createCashbackVoucherDataFor(
    int $rate = 500,
    string $rateType = 'percentage',
    ?int $maxCashback = null,
    ?int $minOrderValue = null,
    string $creditTo = 'wallet',
    int $creditDelayHours = 168,
    bool $requiresOrderCompletion = true
): VoucherData {
    return new VoucherData(
        id: 'test-cashback-voucher',
        code: 'CASHBACK',
        name: 'Cashback Reward',
        description: 'Earn cashback on your purchase',
        type: VoucherType::Cashback,
        value: 0,
        valueConfig: [
            'rate' => $rate,
            'rate_type' => $rateType,
            'max_cashback' => $maxCashback,
            'min_order_value' => $minOrderValue,
            'credit_to' => $creditTo,
            'credit_delay_hours' => $creditDelayHours,
            'requires_order_completion' => $requiresOrderCompletion,
        ],
        creditDestination: $creditTo,
        creditDelayHours: $creditDelayHours,
        currency: 'USD',
        minCartValue: null,
        maxDiscount: null,
        usageLimit: null,
        usageLimitPerUser: null,
        allowsManualRedemption: true,
        ownerId: null,
        ownerType: null,
        startsAt: null,
        expiresAt: null,
        status: VoucherStatus::Active,
        targetDefinition: null,
        metadata: []
    );
}

describe('CashbackVoucherCondition', function (): void {
    describe('basic functionality', function (): void {
        it('creates condition from voucher data', function (): void {
            $voucher = createCashbackVoucherDataFor();
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition)->toBeInstanceOf(CashbackVoucherCondition::class);
        });

        it('has correct name format', function (): void {
            $voucher = createCashbackVoucherDataFor();
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getName())->toBe('voucher_CASHBACK');
        });

        it('has correct type', function (): void {
            $voucher = createCashbackVoucherDataFor();
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getType())->toBe('voucher');
        });
    });

    describe('discount calculation', function (): void {
        it('returns zero discount at checkout', function (): void {
            $voucher = createCashbackVoucherDataFor(rate: 500); // 5%
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(10000); // $100

            // Cashback doesn't reduce cart total
            expect($condition->calculateDiscount($cart))->toBe(0);
        });
    });

    describe('cashback calculation', function (): void {
        it('calculates percentage cashback', function (): void {
            $voucher = createCashbackVoucherDataFor(rate: 500, rateType: 'percentage'); // 5%
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(10000); // $100

            // 5% of $100 = $5 = 500 cents
            expect($condition->calculateCashback($cart))->toBe(500);
        });

        it('calculates fixed cashback', function (): void {
            $voucher = createCashbackVoucherDataFor(rate: 1000, rateType: 'fixed'); // $10
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(5000); // $50

            // Fixed $10 = 1000 cents
            expect($condition->calculateCashback($cart))->toBe(1000);
        });

        it('calculates per-item cashback', function (): void {
            $voucher = createCashbackVoucherDataFor(rate: 100, rateType: 'per_item'); // $1 per item
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(5000, quantity: 5); // 5 items

            // 5 items × $1 = $5 = 500 cents
            expect($condition->calculateCashback($cart))->toBe(500);
        });

        it('respects max cashback limit', function (): void {
            $voucher = createCashbackVoucherDataFor(
                rate: 1000, // 10%
                rateType: 'percentage',
                maxCashback: 500 // Max $5
            );
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(10000); // $100

            // 10% of $100 = $10, but capped at $5
            expect($condition->calculateCashback($cart))->toBe(500);
        });

        it('returns zero when requirements not met', function (): void {
            $voucher = createCashbackVoucherDataFor(
                rate: 500,
                rateType: 'percentage',
                minOrderValue: 10000 // Min $100
            );
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(5000); // $50 < $100

            expect($condition->calculateCashback($cart))->toBe(0);
        });
    });

    describe('requirement checking', function (): void {
        it('meets requirements with no minimum', function (): void {
            $voucher = createCashbackVoucherDataFor(minOrderValue: null);
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(1000); // $10

            expect($condition->meetsRequirements($cart))->toBeTrue();
        });

        it('meets requirements when above minimum', function (): void {
            $voucher = createCashbackVoucherDataFor(minOrderValue: 5000); // Min $50
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(10000); // $100 > $50

            expect($condition->meetsRequirements($cart))->toBeTrue();
        });

        it('does not meet requirements when below minimum', function (): void {
            $voucher = createCashbackVoucherDataFor(minOrderValue: 10000); // Min $100
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(5000); // $50 < $100

            expect($condition->meetsRequirements($cart))->toBeFalse();
        });

        it('meets requirements when exactly at minimum', function (): void {
            $voucher = createCashbackVoucherDataFor(minOrderValue: 5000); // Min $50
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(5000); // $50 = $50

            expect($condition->meetsRequirements($cart))->toBeTrue();
        });
    });

    describe('credit configuration', function (): void {
        it('returns credit destination', function (): void {
            $voucher = createCashbackVoucherDataFor(creditTo: 'wallet');
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getCreditDestination())->toBe('wallet');
        });

        it('returns credit delay hours', function (): void {
            $voucher = createCashbackVoucherDataFor(creditDelayHours: 48);
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getCreditDelayHours())->toBe(48);
        });

        it('returns requires order completion flag', function (): void {
            $voucher = createCashbackVoucherDataFor(requiresOrderCompletion: true);
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->requiresOrderCompletion())->toBeTrue();
        });

        it('returns min order value', function (): void {
            $voucher = createCashbackVoucherDataFor(minOrderValue: 5000);
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getMinOrderValue())->toBe(5000);
        });

        it('returns null min order value when not set', function (): void {
            $voucher = createCashbackVoucherDataFor(minOrderValue: null);
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getMinOrderValue())->toBeNull();
        });

        it('returns max cashback', function (): void {
            $voucher = createCashbackVoucherDataFor(maxCashback: 2500);
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getMaxCashback())->toBe(2500);
        });

        it('returns null max cashback when not set', function (): void {
            $voucher = createCashbackVoucherDataFor(maxCashback: null);
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getMaxCashback())->toBeNull();
        });
    });

    describe('cashback config for processing', function (): void {
        it('returns complete cashback config', function (): void {
            $voucher = createCashbackVoucherDataFor(
                rate: 500,
                rateType: 'percentage',
                creditTo: 'wallet',
                creditDelayHours: 168,
                requiresOrderCompletion: true
            );
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(10000);

            $config = $condition->getCashbackConfig($cart);

            expect($config['amount'])->toBe(500);
            expect($config['credit_to'])->toBe('wallet');
            expect($config['credit_delay_hours'])->toBe(168);
            expect($config['requires_order_completion'])->toBeTrue();
            expect($config['voucher_id'])->toBe('test-cashback-voucher');
            expect($config['voucher_code'])->toBe('CASHBACK');
        });
    });

    describe('discount description', function (): void {
        it('returns percentage cashback description', function (): void {
            $voucher = createCashbackVoucherDataFor(rate: 500, rateType: 'percentage', creditTo: 'wallet');
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(10000);

            expect($condition->getDiscountDescription($cart))->toBe('5% cashback to wallet');
        });

        it('returns fixed cashback description', function (): void {
            $voucher = createCashbackVoucherDataFor(rate: 1000, rateType: 'fixed', creditTo: 'next_order');
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(10000);

            expect($condition->getDiscountDescription($cart))->toBe('RM10 cashback to next order');
        });

        it('returns per-item cashback description', function (): void {
            $voucher = createCashbackVoucherDataFor(rate: 100, rateType: 'per_item', creditTo: 'points');
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createCashbackTestCart(5000);

            expect($condition->getDiscountDescription($cart))->toBe('RM1 cashback per item to points');
        });
    });

    describe('voucher accessors', function (): void {
        it('returns voucher data', function (): void {
            $voucher = createCashbackVoucherDataFor();
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getVoucher())->toBe($voucher);
        });

        it('returns voucher code', function (): void {
            $voucher = createCashbackVoucherDataFor();
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getVoucherCode())->toBe('CASHBACK');
        });

        it('returns value config', function (): void {
            $voucher = createCashbackVoucherDataFor();
            $condition = new CashbackVoucherCondition($voucher, $voucher->valueConfig);

            $config = $condition->getValueConfig();
            expect($config)->toHaveKey('rate');
            expect($config)->toHaveKey('rate_type');
            expect($config)->toHaveKey('credit_to');
        });
    });
});
