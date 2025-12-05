<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Compound\Conditions\BOGOVoucherCondition;
use AIArmada\Vouchers\Compound\Enums\ItemSelectionStrategy;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;

/**
 * Create a test cart with items.
 *
 * @param  array<array{sku: string, price: int, quantity: int}>  $itemsData
 */
function createBogoTestCart(array $itemsData): Cart
{
    $storage = new InMemoryStorage();
    $cart = new Cart($storage, 'test-bogo', events: null);

    foreach ($itemsData as $data) {
        $cart->add(
            id: $data['sku'],
            name: "Product {$data['sku']}",
            price: $data['price'],
            quantity: $data['quantity'],
            attributes: ['sku' => $data['sku']]
        );
    }

    return $cart;
}

/**
 * Create a BOGO voucher data object for testing.
 */
function createBogoVoucherDataFor(
    ItemSelectionStrategy $selection = ItemSelectionStrategy::Cheapest,
    int $discountPercent = 100,
    ?int $maxApplications = null
): VoucherData {
    return new VoucherData(
        id: 'test-bogo-voucher',
        code: 'BOGO',
        name: 'Buy 2 Get 1 Free',
        description: 'Buy 2 shirts, get 1 free',
        type: VoucherType::BuyXGetY,
        value: 0,
        valueConfig: [
            'buy' => [
                'quantity' => 2,
                'product_matcher' => [
                    'type' => 'sku',
                    'skus' => ['SHIRT-001', 'SHIRT-002'],
                ],
            ],
            'get' => [
                'quantity' => 1,
                'discount' => $discountPercent === 100 ? '100%' : "{$discountPercent}%",
                'selection' => $selection->value,
                'product_matcher' => [
                    'type' => 'sku',
                    'skus' => ['SHIRT-001', 'SHIRT-002'],
                ],
            ],
            'max_applications' => $maxApplications,
        ],
        creditDestination: null,
        creditDelayHours: 0,
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

describe('BOGOVoucherCondition', function (): void {
    describe('basic functionality', function (): void {
        it('creates condition from voucher data', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition)->toBeInstanceOf(BOGOVoucherCondition::class);
        });

        it('has correct name format', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getName())->toBe('voucher_BOGO');
        });

        it('has correct type', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getType())->toBe('voucher');
        });
    });

    describe('discount description', function (): void {
        it('returns buy X get Y free description', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBogoTestCart([
                ['sku' => 'SHIRT-001', 'price' => 2000, 'quantity' => 3],
            ]);

            $description = $condition->getDiscountDescription($cart);
            expect($description)->toBe('Buy 2 Get 1 Free');
        });

        it('returns percentage off description', function (): void {
            $voucher = createBogoVoucherDataFor(discountPercent: 50);
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBogoTestCart([
                ['sku' => 'SHIRT-001', 'price' => 2000, 'quantity' => 3],
            ]);

            $description = $condition->getDiscountDescription($cart);
            expect($description)->toBe('Buy 2 Get 1 at 50% Off');
        });
    });

    describe('toArray', function (): void {
        it('returns array representation', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            $array = $condition->toArray();

            expect($array)->toBeArray();
            expect($array['name'])->toBe('voucher_BOGO');
            expect($array['type'])->toBe('voucher');
            expect($array['voucher']['code'])->toBe('BOGO');
            expect($array['is_compound'])->toBeTrue();
        });
    });

    describe('voucher accessors', function (): void {
        it('returns voucher data', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getVoucher())->toBe($voucher);
        });

        it('returns voucher code', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getVoucherCode())->toBe('BOGO');
        });

        it('returns value config', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            $config = $condition->getValueConfig();
            expect($config)->toBeArray();
            expect($config['buy']['quantity'])->toBe(2);
            expect($config['get']['quantity'])->toBe(1);
        });
    });

    describe('requirement checking', function (): void {
        it('meets requirements when enough qualifying items exist', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBogoTestCart([
                ['sku' => 'SHIRT-001', 'price' => 2000, 'quantity' => 3],
            ]);

            expect($condition->meetsRequirements($cart))->toBeTrue();
        });

        it('does not meet requirements when not enough items', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBogoTestCart([
                ['sku' => 'SHIRT-001', 'price' => 2000, 'quantity' => 1],
            ]);

            expect($condition->meetsRequirements($cart))->toBeFalse();
        });

        it('does not meet requirements when items do not match', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBogoTestCart([
                ['sku' => 'OTHER-001', 'price' => 2000, 'quantity' => 5],
            ]);

            expect($condition->meetsRequirements($cart))->toBeFalse();
        });
    });

    describe('discount calculation', function (): void {
        it('calculates buy 2 get 1 free correctly', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBogoTestCart([
                ['sku' => 'SHIRT-001', 'price' => 2000, 'quantity' => 3],
            ]);

            // Buy 2 at $20 each, get 1 free
            $discount = $condition->calculateDiscount($cart);
            expect($discount)->toBe(2000);
        });

        it('applies multiple BOGO applications', function (): void {
            $voucher = createBogoVoucherDataFor();
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBogoTestCart([
                ['sku' => 'SHIRT-001', 'price' => 2000, 'quantity' => 6],
            ]);

            // 6 items = 2 complete BOGO cycles (buy 2 get 1 x 2)
            // Discount should be $40 (2 x $20)
            $discount = $condition->calculateDiscount($cart);
            expect($discount)->toBe(4000);
        });

        it('respects max applications limit', function (): void {
            $voucher = createBogoVoucherDataFor(maxApplications: 1);
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBogoTestCart([
                ['sku' => 'SHIRT-001', 'price' => 2000, 'quantity' => 9],
            ]);

            // Should only apply once even though 3 cycles are possible
            $discount = $condition->calculateDiscount($cart);
            expect($discount)->toBe(2000);
        });

        it('applies partial discount percent', function (): void {
            $voucher = createBogoVoucherDataFor(discountPercent: 50);
            $condition = new BOGOVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBogoTestCart([
                ['sku' => 'SHIRT-001', 'price' => 2000, 'quantity' => 3],
            ]);

            // Buy 2, get 1 at 50% off ($10)
            $discount = $condition->calculateDiscount($cart);
            expect($discount)->toBe(1000);
        });
    });
});
