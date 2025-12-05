<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Compound\Conditions\TieredVoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;

/**
 * Create a test cart with a specified total value.
 *
 * @param  int  $totalValue  Total cart value in cents
 */
function createTieredTestCart(int $totalValue): Cart
{
    $storage = new InMemoryStorage();
    $cart = new Cart($storage, 'test-tiered', events: null);

    $cart->add(
        id: 'PRODUCT-001',
        name: 'Product',
        price: $totalValue,
        quantity: 1,
        attributes: ['sku' => 'PRODUCT-001']
    );

    return $cart;
}

/**
 * Create a tiered voucher data object for testing.
 *
 * @param  array<int, array{min_value: int, discount: string, label: string}>|null  $tiers
 */
function createTieredVoucherDataFor(
    ?array $tiers = null,
    string $calculationBase = 'subtotal',
    bool $applyHighestOnly = true
): VoucherData {
    if ($tiers === null) {
        $tiers = [
            ['min_value' => 10000, 'discount' => '-5%', 'label' => 'Bronze'],
            ['min_value' => 20000, 'discount' => '-10%', 'label' => 'Silver'],
            ['min_value' => 50000, 'discount' => '-15%', 'label' => 'Gold'],
        ];
    }

    return new VoucherData(
        id: 'test-tiered-voucher',
        code: 'TIERED',
        name: 'Tiered Discount',
        description: 'Get more discount when you spend more',
        type: VoucherType::Tiered,
        value: 0,
        valueConfig: [
            'tiers' => $tiers,
            'calculation_base' => $calculationBase,
            'apply_highest_only' => $applyHighestOnly,
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

describe('TieredVoucherCondition', function (): void {
    describe('basic functionality', function (): void {
        it('creates condition from voucher data', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition)->toBeInstanceOf(TieredVoucherCondition::class);
        });

        it('has correct name format', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getName())->toBe('voucher_TIERED');
        });

        it('has correct type', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getType())->toBe('voucher');
        });
    });

    describe('tier retrieval', function (): void {
        it('returns all configured tiers', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $tiers = $condition->getTiers();
            expect($tiers)->toHaveCount(3);
            expect($tiers[0]['label'])->toBe('Bronze');
            expect($tiers[1]['label'])->toBe('Silver');
            expect($tiers[2]['label'])->toBe('Gold');
        });

        it('returns empty array when no tiers configured', function (): void {
            $voucher = createTieredVoucherDataFor(tiers: []);
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getTiers())->toBeEmpty();
        });
    });

    describe('applicable tier', function (): void {
        it('returns null when cart value is below all tiers', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(5000); // $50 - below Bronze ($100)

            expect($condition->getApplicableTier($cart))->toBeNull();
        });

        it('returns Bronze tier for cart at minimum threshold', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(10000); // $100 - exactly Bronze

            $tier = $condition->getApplicableTier($cart);
            expect($tier)->not->toBeNull();
            expect($tier['label'])->toBe('Bronze');
        });

        it('returns Silver tier for cart between Silver and Gold', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(30000); // $300 - between Silver ($200) and Gold ($500)

            $tier = $condition->getApplicableTier($cart);
            expect($tier)->not->toBeNull();
            expect($tier['label'])->toBe('Silver');
        });

        it('returns highest applicable tier (Gold)', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(75000); // $750 - above Gold ($500)

            $tier = $condition->getApplicableTier($cart);
            expect($tier)->not->toBeNull();
            expect($tier['label'])->toBe('Gold');
        });
    });

    describe('next tier', function (): void {
        it('returns Bronze as next tier when below all tiers', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(5000);

            $nextTier = $condition->getNextTier($cart);
            expect($nextTier)->not->toBeNull();
            expect($nextTier['label'])->toBe('Bronze');
        });

        it('returns Silver as next tier when at Bronze', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(15000); // $150 - in Bronze tier

            $nextTier = $condition->getNextTier($cart);
            expect($nextTier)->not->toBeNull();
            expect($nextTier['label'])->toBe('Silver');
        });

        it('returns null when at highest tier', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(75000); // $750 - above Gold

            expect($condition->getNextTier($cart))->toBeNull();
        });
    });

    describe('amount to next tier', function (): void {
        it('calculates amount needed to reach Bronze', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(5000); // $50

            // Need $50 more to reach Bronze ($100)
            expect($condition->getAmountToNextTier($cart))->toBe(5000);
        });

        it('calculates amount needed to reach Silver from Bronze', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(15000); // $150 in Bronze

            // Need $50 more to reach Silver ($200)
            expect($condition->getAmountToNextTier($cart))->toBe(5000);
        });

        it('returns zero when at highest tier', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(75000); // Above Gold

            expect($condition->getAmountToNextTier($cart))->toBe(0);
        });
    });

    describe('requirement checking', function (): void {
        it('meets requirements when cart qualifies for a tier', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(15000); // $150 in Bronze

            expect($condition->meetsRequirements($cart))->toBeTrue();
        });

        it('does not meet requirements when cart is below all tiers', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(5000); // $50 below Bronze

            expect($condition->meetsRequirements($cart))->toBeFalse();
        });
    });

    describe('discount calculation', function (): void {
        it('calculates Bronze tier percentage discount', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(10000); // $100 at Bronze (5% off)

            // 5% of $100 = $5
            expect($condition->calculateDiscount($cart))->toBe(500);
        });

        it('calculates Silver tier percentage discount', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(30000); // $300 at Silver (10% off)

            // 10% of $300 = $30
            expect($condition->calculateDiscount($cart))->toBe(3000);
        });

        it('calculates Gold tier percentage discount', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(60000); // $600 at Gold (15% off)

            // 15% of $600 = $90
            expect($condition->calculateDiscount($cart))->toBe(9000);
        });

        it('calculates fixed discount tiers', function (): void {
            $voucher = createTieredVoucherDataFor(tiers: [
                ['min_value' => 10000, 'discount' => '-500', 'label' => 'Small'],
                ['min_value' => 20000, 'discount' => '-1500', 'label' => 'Medium'],
            ]);
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(25000); // $250 at Medium

            // Fixed $15 discount
            expect($condition->calculateDiscount($cart))->toBe(1500);
        });

        it('returns zero discount when below all tiers', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(5000); // $50 below Bronze

            expect($condition->calculateDiscount($cart))->toBe(0);
        });

        it('caps fixed discount at cart value', function (): void {
            $voucher = createTieredVoucherDataFor(tiers: [
                ['min_value' => 1000, 'discount' => '-5000', 'label' => 'Huge'],
            ]);
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(2000); // $20 cart, $50 discount

            // Should cap at cart value
            expect($condition->calculateDiscount($cart))->toBe(2000);
        });
    });

    describe('discount description', function (): void {
        it('returns percentage discount description', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(30000); // Silver tier

            expect($condition->getDiscountDescription($cart))->toBe('Silver: 10% off');
        });

        it('returns fixed discount description', function (): void {
            $voucher = createTieredVoucherDataFor(tiers: [
                ['min_value' => 10000, 'discount' => '-500', 'label' => 'Small'],
            ]);
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(15000);

            expect($condition->getDiscountDescription($cart))->toBe('Small: RM5 off');
        });

        it('returns unlock message when below all tiers', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createTieredTestCart(5000);

            expect($condition->getDiscountDescription($cart))->toBe('Spend RM100+ to unlock discounts');
        });
    });

    describe('voucher accessors', function (): void {
        it('returns voucher data', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getVoucher())->toBe($voucher);
        });

        it('returns voucher code', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getVoucherCode())->toBe('TIERED');
        });

        it('returns value config', function (): void {
            $voucher = createTieredVoucherDataFor();
            $condition = new TieredVoucherCondition($voucher, $voucher->valueConfig);

            $config = $condition->getValueConfig();
            expect($config)->toHaveKey('tiers');
            expect($config['tiers'])->toHaveCount(3);
        });
    });
});
