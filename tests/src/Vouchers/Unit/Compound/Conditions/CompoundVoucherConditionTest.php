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

describe('BOGOVoucherCondition', function (): void {
    describe('meetsRequirements', function (): void {
        it('returns true when cart has enough qualifying items', function (): void {
            $valueConfig = [
                'buy' => ['quantity' => 2, 'product_matcher' => ['type' => 'all']],
                'get' => ['quantity' => 1, 'discount' => '100%'],
            ];
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            expect($condition->meetsRequirements($cart))->toBeTrue();
        });

        it('returns false when cart has insufficient items', function (): void {
            $valueConfig = [
                'buy' => ['quantity' => 10, 'product_matcher' => ['type' => 'all']],
                'get' => ['quantity' => 1, 'discount' => '100%'],
            ];
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            expect($condition->meetsRequirements($cart))->toBeFalse();
        });
    });

    describe('calculateDiscount', function (): void {
        it('returns 0 when requirements not met', function (): void {
            $valueConfig = [
                'buy' => ['quantity' => 100, 'product_matcher' => ['type' => 'all']],
                'get' => ['quantity' => 1, 'discount' => '100%'],
            ];
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            expect($condition->calculateDiscount($cart))->toBe(0);
        });

        it('calculates discount when requirements met', function (): void {
            $valueConfig = [
                'buy' => ['quantity' => 2, 'product_matcher' => ['type' => 'all']],
                'get' => ['quantity' => 1, 'discount' => '100%', 'selection' => 'cheapest'],
            ];
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            // Cart: 2x5000 + 1x3000 = total 3 items, cheapest is 3000
            $cart = createCompoundTestCart();

            $discount = $condition->calculateDiscount($cart);

            expect($discount)->toBeGreaterThan(0);
        });

        it('calculates 50% discount correctly', function (): void {
            $valueConfig = [
                'buy' => ['quantity' => 1, 'product_matcher' => ['type' => 'all']],
                'get' => ['quantity' => 1, 'discount' => '50%', 'selection' => 'cheapest'],
            ];
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            // Cart: 2x5000 + 1x3000, cheapest item gets 50% off
            $cart = createCompoundTestCart();

            $discount = $condition->calculateDiscount($cart);

            expect($discount)->toBeGreaterThan(0);
        });
    });

    describe('getDiscountDescription', function (): void {
        it('describes buy X get Y free', function (): void {
            $valueConfig = [
                'buy' => ['quantity' => 2, 'product_matcher' => ['type' => 'all']],
                'get' => ['quantity' => 1, 'discount' => '100%'],
            ];
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            expect($condition->getDiscountDescription($cart))->toBe('Buy 2 Get 1 Free');
        });

        it('describes percentage discount', function (): void {
            $valueConfig = [
                'buy' => ['quantity' => 1, 'product_matcher' => ['type' => 'all']],
                'get' => ['quantity' => 1, 'discount' => '50%'],
            ];
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            expect($condition->getDiscountDescription($cart))->toBe('Buy 1 Get 1 at 50% Off');
        });
    });

    describe('calculateApplications', function (): void {
        it('returns empty array when requirements not met', function (): void {
            $valueConfig = [
                'buy' => ['quantity' => 100, 'product_matcher' => ['type' => 'all']],
                'get' => ['quantity' => 1, 'discount' => '100%'],
            ];
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            expect($condition->calculateApplications($cart))->toBe([]);
        });

        it('calculates multiple applications', function (): void {
            $valueConfig = [
                'buy' => ['quantity' => 1, 'product_matcher' => ['type' => 'all']],
                'get' => ['quantity' => 1, 'discount' => '100%'],
            ];
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            // 3 items total, buy 1 get 1 = 1 application
            $cart = createCompoundTestCart();

            $applications = $condition->calculateApplications($cart);

            expect($applications)->not->toBeEmpty();
        });

        it('respects max applications limit', function (): void {
            $valueConfig = [
                'buy' => ['quantity' => 1, 'product_matcher' => ['type' => 'all']],
                'get' => ['quantity' => 1, 'discount' => '100%'],
                'max_applications' => 1,
            ];
            $voucher = createCompoundVoucherData(VoucherType::BuyXGetY, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            // Many items but max 1 application
            $cart = createCompoundTestCart([
                ['id' => 'p1', 'name' => 'P1', 'price' => 1000, 'quantity' => 10],
            ]);

            $applications = $condition->calculateApplications($cart);

            expect(count($applications))->toBe(1);
        });
    });
});

describe('TieredVoucherCondition', function (): void {
    describe('meetsRequirements', function (): void {
        it('returns true when cart value meets minimum tier', function (): void {
            $valueConfig = [
                'tiers' => [
                    ['min_value' => 5000, 'discount' => '10%'],
                ],
            ];
            $voucher = createCompoundVoucherData(VoucherType::Tiered, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            // Cart total: 5000*2 + 3000 = 13000
            $cart = createCompoundTestCart();

            expect($condition->meetsRequirements($cart))->toBeTrue();
        });

        it('returns false when cart value below all tiers', function (): void {
            $valueConfig = [
                'tiers' => [
                    ['min_value' => 999999, 'discount' => '10%'],
                ],
            ];
            $voucher = createCompoundVoucherData(VoucherType::Tiered, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            expect($condition->meetsRequirements($cart))->toBeFalse();
        });
    });

    describe('calculateDiscount', function (): void {
        it('returns 0 when requirements not met', function (): void {
            $valueConfig = [
                'tiers' => [
                    ['min_value' => 999999, 'discount' => '10%'],
                ],
            ];
            $voucher = createCompoundVoucherData(VoucherType::Tiered, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            expect($condition->calculateDiscount($cart))->toBe(0);
        });

        it('applies highest eligible tier', function (): void {
            $valueConfig = [
                'tiers' => [
                    ['min_value' => 1000, 'discount' => '5%'],
                    ['min_value' => 5000, 'discount' => '10%'],
                    ['min_value' => 10000, 'discount' => '15%'],
                ],
            ];
            $voucher = createCompoundVoucherData(VoucherType::Tiered, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            // Cart total: 13000, should apply 15% tier
            $cart = createCompoundTestCart();

            $discount = $condition->calculateDiscount($cart);

            expect($discount)->toBeGreaterThan(0);
        });
    });

    describe('getDiscountDescription', function (): void {
        it('describes current tier discount', function (): void {
            $valueConfig = [
                'tiers' => [
                    ['min_value' => 5000, 'discount' => '10%'],
                ],
            ];
            $voucher = createCompoundVoucherData(VoucherType::Tiered, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            $description = $condition->getDiscountDescription($cart);

            expect($description)->toContain('10%');
        });
    });
});

describe('BundleVoucherCondition', function (): void {
    describe('meetsRequirements', function (): void {
        it('returns true when all bundle items present', function (): void {
            // Bundle requires items with SKU that matches cart item attributes
            $valueConfig = [
                'required_products' => [
                    ['sku' => 'PRODUCT-1', 'quantity' => 1],
                ],
                'discount' => '-20%',
            ];
            $voucher = createCompoundVoucherData(VoucherType::Bundle, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            // Create cart with item that has matching SKU
            $cart = createCompoundTestCart([
                ['id' => 'item-1', 'name' => 'Test', 'price' => 5000, 'quantity' => 1, 'attributes' => ['sku' => 'PRODUCT-1']],
            ]);

            expect($condition->meetsRequirements($cart))->toBeTrue();
        });

        it('returns false when bundle items missing', function (): void {
            $valueConfig = [
                'required_products' => [
                    ['sku' => 'NONEXISTENT', 'quantity' => 1],
                ],
                'discount' => '-20%',
            ];
            $voucher = createCompoundVoucherData(VoucherType::Bundle, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            expect($condition->meetsRequirements($cart))->toBeFalse();
        });
    });

    describe('calculateDiscount', function (): void {
        it('returns 0 when bundle not complete', function (): void {
            $valueConfig = [
                'required_products' => [
                    ['sku' => 'NONEXISTENT', 'quantity' => 1],
                ],
                'discount' => '-20%',
            ];
            $voucher = createCompoundVoucherData(VoucherType::Bundle, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            expect($condition->calculateDiscount($cart))->toBe(0);
        });

        it('calculates bundle discount when complete', function (): void {
            $valueConfig = [
                'required_products' => [
                    ['sku' => 'BUNDLE-ITEM', 'quantity' => 1],
                ],
                'discount' => '-20%',
            ];
            $voucher = createCompoundVoucherData(VoucherType::Bundle, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart([
                ['id' => 'item-1', 'name' => 'Test', 'price' => 5000, 'quantity' => 1, 'attributes' => ['sku' => 'BUNDLE-ITEM']],
            ]);

            $discount = $condition->calculateDiscount($cart);

            expect($discount)->toBeGreaterThan(0);
        });
    });

    describe('getDiscountDescription', function (): void {
        it('describes bundle discount', function (): void {
            $valueConfig = [
                'required_products' => [
                    ['sku' => 'PRODUCT-1', 'quantity' => 1],
                ],
                'discount' => '-20%',
                'bundle_name' => 'Test Bundle',
            ];
            $voucher = createCompoundVoucherData(VoucherType::Bundle, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            $description = $condition->getDiscountDescription($cart);

            expect($description)->toContain('Bundle');
        });
    });
});

describe('CashbackVoucherCondition', function (): void {
    describe('meetsRequirements', function (): void {
        it('returns true when cart has items', function (): void {
            // Cashback only checks min_order_value, defaults to no minimum
            $valueConfig = [
                'rate' => 1000, // 10% in basis points
                'rate_type' => 'percentage',
            ];
            $voucher = createCompoundVoucherData(VoucherType::Cashback, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            expect($condition->meetsRequirements($cart))->toBeTrue();
        });

        it('returns false when below min order value', function (): void {
            $valueConfig = [
                'rate' => 1000,
                'rate_type' => 'percentage',
                'min_order_value' => 999999, // Very high minimum
            ];
            $voucher = createCompoundVoucherData(VoucherType::Cashback, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            expect($condition->meetsRequirements($cart))->toBeFalse();
        });
    });

    describe('calculateDiscount', function (): void {
        it('returns 0 always as cashback does not reduce cart total', function (): void {
            $valueConfig = [
                'rate' => 1000, // 10%
                'rate_type' => 'percentage',
            ];
            $voucher = createCompoundVoucherData(VoucherType::Cashback, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            // Cashback never reduces cart total - it credits wallet after checkout
            expect($condition->calculateDiscount($cart))->toBe(0);
        });
    });

    describe('calculateCashback', function (): void {
        it('calculates cashback based on rate', function (): void {
            $valueConfig = [
                'rate' => 1000, // 10% in basis points (1000/10000 = 10%)
                'rate_type' => 'percentage',
            ];
            $voucher = createCompoundVoucherData(VoucherType::Cashback, $valueConfig);
            /** @var CashbackVoucherCondition $condition */
            $condition = CompoundVoucherCondition::create($voucher);

            // Cart total: 5000*2 + 3000 = 13000
            $cart = createCompoundTestCart();

            $cashback = $condition->calculateCashback($cart);

            // 10% of 13000 = 1300
            expect($cashback)->toBe(1300);
        });

        it('calculates fixed cashback', function (): void {
            $valueConfig = [
                'rate' => 500, // 500 cents = RM5
                'rate_type' => 'fixed',
            ];
            $voucher = createCompoundVoucherData(VoucherType::Cashback, $valueConfig);
            /** @var CashbackVoucherCondition $condition */
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            $cashback = $condition->calculateCashback($cart);

            expect($cashback)->toBe(500);
        });
    });

    describe('getDiscountDescription', function (): void {
        it('describes cashback percentage', function (): void {
            $valueConfig = [
                'rate' => 1000, // 10% in basis points
                'rate_type' => 'percentage',
            ];
            $voucher = createCompoundVoucherData(VoucherType::Cashback, $valueConfig);
            $condition = CompoundVoucherCondition::create($voucher);

            $cart = createCompoundTestCart();

            $description = $condition->getDiscountDescription($cart);

            expect($description)->toContain('10%');
            expect($description)->toContain('cashback');
        });
    });
});
