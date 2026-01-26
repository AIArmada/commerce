<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\Compound\Conditions\BundleVoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\VoucherStatus;

/**
 * Create a test cart with items.
 *
 * @param  array<array{sku: string, price: int, quantity: int}>  $itemsData
 */
function createBundleTestCart(array $itemsData): Cart
{
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-bundle', events: null);

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
 * Create a bundle voucher data object for testing.
 *
 * @param  array<int, array{sku: string, quantity: int}>|null  $requiredProducts
 */
function createBundleVoucherDataFor(
    ?array $requiredProducts = null,
    string $discount = '-20%',
    string $discountAppliesTo = 'bundle',
    bool $allowMultiples = false,
    string $bundleName = 'Work From Home Kit'
): VoucherData {
    if ($requiredProducts === null) {
        $requiredProducts = [
            ['sku' => 'LAPTOP-001', 'quantity' => 1],
            ['sku' => 'MOUSE-001', 'quantity' => 1],
            ['sku' => 'KEYBOARD-001', 'quantity' => 1],
        ];
    }

    return new VoucherData(
        id: 'test-bundle-voucher',
        code: 'BUNDLE',
        name: 'Bundle Discount',
        description: 'Buy together and save',
        type: VoucherType::Bundle,
        value: 0,
        valueConfig: [
            'required_products' => $requiredProducts,
            'discount' => $discount,
            'discount_applies_to' => $discountAppliesTo,
            'allow_multiples' => $allowMultiples,
            'bundle_name' => $bundleName,
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
        status: VoucherStatus::fromString(Active::class),
        targetDefinition: null,
        metadata: []
    );
}

describe('BundleVoucherCondition', function (): void {
    describe('basic functionality', function (): void {
        it('creates condition from voucher data', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition)->toBeInstanceOf(BundleVoucherCondition::class);
        });

        it('has correct name format', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getName())->toBe('voucher_BUNDLE');
        });

        it('has correct type', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getType())->toBe('voucher');
        });
    });

    describe('required products', function (): void {
        it('returns configured required products', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $products = $condition->getRequiredProducts();
            expect($products)->toHaveCount(3);
            expect($products[0]['sku'])->toBe('LAPTOP-001');
            expect($products[1]['sku'])->toBe('MOUSE-001');
            expect($products[2]['sku'])->toBe('KEYBOARD-001');
        });

        it('returns empty array when no products configured', function (): void {
            $voucher = createBundleVoucherDataFor(requiredProducts: []);
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getRequiredProducts())->toBeEmpty();
        });
    });

    describe('complete bundles counting', function (): void {
        it('counts zero bundles when cart is empty', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([]);

            expect($condition->countCompleteBundles($cart))->toBe(0);
        });

        it('counts zero bundles when product is missing', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1],
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 1],
                // Missing KEYBOARD-001
            ]);

            expect($condition->countCompleteBundles($cart))->toBe(0);
        });

        it('counts one bundle when all products present', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1],
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 1],
                ['sku' => 'KEYBOARD-001', 'price' => 8000, 'quantity' => 1],
            ]);

            expect($condition->countCompleteBundles($cart))->toBe(1);
        });

        it('counts one bundle only when multiples disabled', function (): void {
            $voucher = createBundleVoucherDataFor(allowMultiples: false);
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 3],
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 3],
                ['sku' => 'KEYBOARD-001', 'price' => 8000, 'quantity' => 3],
            ]);

            expect($condition->countCompleteBundles($cart))->toBe(1);
        });

        it('counts multiple bundles when multiples enabled', function (): void {
            $voucher = createBundleVoucherDataFor(allowMultiples: true);
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 3],
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 3],
                ['sku' => 'KEYBOARD-001', 'price' => 8000, 'quantity' => 3],
            ]);

            expect($condition->countCompleteBundles($cart))->toBe(3);
        });

        it('counts limited by lowest quantity product', function (): void {
            $voucher = createBundleVoucherDataFor(allowMultiples: true);
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 5],
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 2], // Limiting factor
                ['sku' => 'KEYBOARD-001', 'price' => 8000, 'quantity' => 4],
            ]);

            expect($condition->countCompleteBundles($cart))->toBe(2);
        });
    });

    describe('missing products', function (): void {
        it('returns all missing products', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1],
            ]);

            $missing = $condition->getMissingProducts($cart);
            expect($missing)->toHaveCount(2);
            expect($missing[0]['sku'])->toBe('MOUSE-001');
            expect($missing[0]['quantity_needed'])->toBe(1);
            expect($missing[1]['sku'])->toBe('KEYBOARD-001');
            expect($missing[1]['quantity_needed'])->toBe(1);
        });

        it('returns empty array when bundle is complete', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1],
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 1],
                ['sku' => 'KEYBOARD-001', 'price' => 8000, 'quantity' => 1],
            ]);

            expect($condition->getMissingProducts($cart))->toBeEmpty();
        });
    });

    describe('bundle value', function (): void {
        it('calculates total bundle value', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1], // $1000
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 1],   // $50
                ['sku' => 'KEYBOARD-001', 'price' => 8000, 'quantity' => 1], // $80
            ]);

            // Bundle value: $1000 + $50 + $80 = $1130 = 113000 cents
            expect($condition->getBundleValue($cart))->toBe(113000);
        });
    });

    describe('requirement checking', function (): void {
        it('meets requirements with complete bundle', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1],
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 1],
                ['sku' => 'KEYBOARD-001', 'price' => 8000, 'quantity' => 1],
            ]);

            expect($condition->meetsRequirements($cart))->toBeTrue();
        });

        it('does not meet requirements with incomplete bundle', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1],
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 1],
            ]);

            expect($condition->meetsRequirements($cart))->toBeFalse();
        });
    });

    describe('discount calculation', function (): void {
        it('calculates percentage discount on bundle', function (): void {
            $voucher = createBundleVoucherDataFor(discount: '-20%');
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1], // $1000
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 1],   // $50
                ['sku' => 'KEYBOARD-001', 'price' => 8000, 'quantity' => 1], // $80
            ]);

            // 20% off $1130 = $226 = 22600 cents
            expect($condition->calculateDiscount($cart))->toBe(22600);
        });

        it('calculates fixed discount on bundle', function (): void {
            $voucher = createBundleVoucherDataFor(discount: '-5000'); // $50 off
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1],
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 1],
                ['sku' => 'KEYBOARD-001', 'price' => 8000, 'quantity' => 1],
            ]);

            expect($condition->calculateDiscount($cart))->toBe(5000);
        });

        it('calculates discount for multiple bundles', function (): void {
            $voucher = createBundleVoucherDataFor(discount: '-20%', allowMultiples: true);
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 2],
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 2],
                ['sku' => 'KEYBOARD-001', 'price' => 8000, 'quantity' => 2],
            ]);

            // 2 bundles × $1130 × 20% = $452 = 45200 cents
            expect($condition->calculateDiscount($cart))->toBe(45200);
        });

        it('returns zero discount when bundle incomplete', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1],
            ]);

            expect($condition->calculateDiscount($cart))->toBe(0);
        });
    });

    describe('discount description', function (): void {
        it('returns percentage off description', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1],
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 1],
                ['sku' => 'KEYBOARD-001', 'price' => 8000, 'quantity' => 1],
            ]);

            expect($condition->getDiscountDescription($cart))->toBe('Work From Home Kit: 20% off');
        });

        it('returns fixed discount description', function (): void {
            $voucher = createBundleVoucherDataFor(discount: '-5000');
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1],
                ['sku' => 'MOUSE-001', 'price' => 5000, 'quantity' => 1],
                ['sku' => 'KEYBOARD-001', 'price' => 8000, 'quantity' => 1],
            ]);

            expect($condition->getDiscountDescription($cart))->toBe('Work From Home Kit: RM50 off');
        });

        it('returns completion message when bundle incomplete', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $cart = createBundleTestCart([
                ['sku' => 'LAPTOP-001', 'price' => 100000, 'quantity' => 1],
            ]);

            expect($condition->getDiscountDescription($cart))->toBe('Work From Home Kit: Add 2 more item(s) to complete');
        });
    });

    describe('voucher accessors', function (): void {
        it('returns voucher data', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getVoucher())->toBe($voucher);
        });

        it('returns voucher code', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            expect($condition->getVoucherCode())->toBe('BUNDLE');
        });

        it('returns value config', function (): void {
            $voucher = createBundleVoucherDataFor();
            $condition = new BundleVoucherCondition($voucher, $voucher->valueConfig);

            $config = $condition->getValueConfig();
            expect($config)->toHaveKey('required_products');
            expect($config['required_products'])->toHaveCount(3);
        });
    });
});
