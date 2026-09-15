<?php

declare(strict_types=1);

namespace Tests\Vouchers\Unit\Compound\Conditions;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Compound\Conditions\BOGOVoucherCondition;
use AIArmada\Vouchers\Compound\Conditions\BundleVoucherCondition;
use AIArmada\Vouchers\Compound\Conditions\CashbackVoucherCondition;
use AIArmada\Vouchers\Compound\Conditions\CompoundVoucherCondition;
use AIArmada\Vouchers\Compound\Conditions\TieredVoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;

/**
 * Create a cart for compound voucher testing.
 */
function createCompoundTestCart(array $items = []): Cart
{
    $cart = new Cart(new InMemoryStorage, 'compound-test-' . uniqid());

    if (empty($items)) {
        $items = [
            [
                'id' => 'product-1',
                'name' => 'Test Product 1',
                'price' => 5000,
                'quantity' => 2,
                'attributes' => ['category' => 'shirts'],
            ],
            [
                'id' => 'product-2',
                'name' => 'Test Product 2',
                'price' => 3000,
                'quantity' => 1,
                'attributes' => ['category' => 'shirts'],
            ],
        ];
    }

    foreach ($items as $item) {
        $cart->add($item);
    }

    return $cart;
}

/**
 * Create voucher data for testing.
 */
function createCompoundVoucherData(VoucherType $type, array $valueConfig = []): VoucherData
{
    return VoucherData::fromArray([
        'id' => 'voucher-' . uniqid(),
        'code' => 'COMPOUND-' . mb_strtoupper(uniqid()),
        'type' => $type->value,
        'value' => 0,
        'value_config' => $valueConfig,
        'usage_limit' => 100,
        'usage_count' => 0,
        'is_active' => true,
        'expires_at' => null,
        'starts_at' => null,
        'metadata' => [],
    ]);
}

describe('CompoundVoucherCondition', function (): void {
    describe('create factory method', function (): void {
        it('creates BOGOVoucherCondition for BuyXGetY type', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, [
                'buy' => ['quantity' => 2, 'product_matcher' => ['type' => 'all']],
                'get' => ['quantity' => 1, 'discount' => '100%'],
            ]);

            $condition = CompoundVoucherCondition::create($voucher);

            expect($condition)->toBeInstanceOf(BOGOVoucherCondition::class);
        });

        it('creates TieredVoucherCondition for Tiered type', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::Tiered, [
                'tiers' => [
                    ['min_value' => 5000, 'discount' => '10%'],
                ],
            ]);

            $condition = CompoundVoucherCondition::create($voucher);

            expect($condition)->toBeInstanceOf(TieredVoucherCondition::class);
        });

        it('creates BundleVoucherCondition for Bundle type', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::Bundle, [
                'bundle_items' => [
                    ['product_matcher' => ['type' => 'all'], 'quantity' => 1],
                ],
            ]);

            $condition = CompoundVoucherCondition::create($voucher);

            expect($condition)->toBeInstanceOf(BundleVoucherCondition::class);
        });

        it('creates CashbackVoucherCondition for Cashback type', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::Cashback, [
                'cashback_rate' => 10,
            ]);

            $condition = CompoundVoucherCondition::create($voucher);

            expect($condition)->toBeInstanceOf(CashbackVoucherCondition::class);
        });

        it('returns null for non-compound types', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::Percentage);

            $condition = CompoundVoucherCondition::create($voucher);

            expect($condition)->toBeNull();
        });

        it('accepts custom order parameter', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY);

            $condition = CompoundVoucherCondition::create($voucher, 5);

            expect($condition->getOrder())->toBe(5);
        });

        it('accepts dynamic parameter', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY);

            $condition = CompoundVoucherCondition::create($voucher, 0, false);

            expect($condition->isDynamic())->toBeFalse();
        });
    });

    describe('base methods', function (): void {
        it('returns voucher data', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY);
            $condition = CompoundVoucherCondition::create($voucher);

            expect($condition->getVoucher())->toBe($voucher);
        });

        it('returns voucher code', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY);
            $condition = CompoundVoucherCondition::create($voucher);

            expect($condition->getVoucherCode())->toBe($voucher->code);
        });

        it('returns value config', function (): void {
            $valueConfig = ['buy' => ['quantity' => 2]];
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            expect($condition->getValueConfig())->toBe($valueConfig);
        });

        it('returns rule factory key', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY);
            $condition = CompoundVoucherCondition::create($voucher);

            expect($condition->getRuleFactoryKey())->toBe('compound_voucher');
        });

        it('returns rule factory context', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY);
            $condition = CompoundVoucherCondition::create($voucher);

            $context = $condition->getRuleFactoryContext();

            expect($context)->toHaveKey('voucher_code', $voucher->code);
            expect($context)->toHaveKey('voucher_id', $voucher->id);
            expect($context)->toHaveKey('voucher_type', VoucherType::BuyXGetY->value);
        });

        it('returns name with voucher code prefix', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY);
            $condition = CompoundVoucherCondition::create($voucher);

            expect($condition->getName())->toBe("voucher_{$voucher->code}");
        });

        it('returns type as voucher', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY);
            $condition = CompoundVoucherCondition::create($voucher);

            expect($condition->getType())->toBe('voucher');
        });
    });

    describe('toArray', function (): void {
        it('serializes to array', function (): void {
            $valueConfig = ['buy' => ['quantity' => 2]];
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher, 3, true);

            $array = $condition->toArray();

            expect($array['name'])->toBe("voucher_{$voucher->code}");
            expect($array['type'])->toBe('voucher');
            expect($array['voucher']['id'])->toBe($voucher->id);
            expect($array['voucher']['code'])->toBe($voucher->code);
            expect($array['voucher']['type'])->toBe(VoucherType::BuyXGetY->value);
            expect($array['value_config'])->toBe($valueConfig);
            expect($array['order'])->toBe(3);
            expect($array['is_dynamic'])->toBeTrue();
            expect($array['is_compound'])->toBeTrue();
        });
    });

    describe('toCartCondition', function (): void {
        it('converts to cart condition', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY);
            $condition = CompoundVoucherCondition::create($voucher);

            $cartCondition = $condition->toCartCondition();

            expect($cartCondition)->toBeInstanceOf(CartCondition::class);
        });

        it('caches cart condition on subsequent calls', function (): void {
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY);
            $condition = CompoundVoucherCondition::create($voucher);

            $cartCondition1 = $condition->toCartCondition();
            $cartCondition2 = $condition->toCartCondition();

            expect($cartCondition1)->toBe($cartCondition2);
        });
    });
});

/* BOGO/Tiered/Bundle/Cashback behavior sections removed; superseded by dedicated condition test files. */
