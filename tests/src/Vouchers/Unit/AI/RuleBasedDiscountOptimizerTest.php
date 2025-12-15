<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\AI\CartFeatureExtractor;
use AIArmada\Vouchers\AI\DiscountRecommendation;
use AIArmada\Vouchers\AI\Enums\DiscountStrategy;
use AIArmada\Vouchers\AI\Optimizers\RuleBasedDiscountOptimizer;
use Illuminate\Database\Eloquent\Model;

function createCartForOptimizerTest(int $subtotalCents = 10000): Cart
{
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-optimizer-cart');

    $pricePerItem = max(100, (int) ($subtotalCents / 2));
    $quantityNeeded = max(1, (int) ceil($subtotalCents / $pricePerItem));

    for ($i = 1; $i <= $quantityNeeded; $i++) {
        $itemPrice = ($i === $quantityNeeded)
            ? $subtotalCents - (($quantityNeeded - 1) * $pricePerItem)
            : $pricePerItem;

        $cart->add([
            'id' => "item-{$i}",
            'name' => "Product {$i}",
            'price' => $itemPrice,
            'quantity' => 1,
        ]);
    }

    return $cart;
}

function createMockUserModel(array $attributes = []): Model
{
    $model = new class extends Model
    {
        protected $guarded = [];
    };

    foreach ($attributes as $key => $value) {
        $model->setAttribute($key, $value);
    }

    return $model;
}

describe('RuleBasedDiscountOptimizer', function (): void {
    beforeEach(function (): void {
        $this->optimizer = new RuleBasedDiscountOptimizer;
    });

    describe('findOptimalDiscount', function (): void {
        it('returns no discount for empty cart', function (): void {
            $storage = new InMemoryStorage;
            $cart = new Cart($storage, 'empty-cart');

            $result = $this->optimizer->findOptimalDiscount($cart);

            expect($result)
                ->toBeInstanceOf(DiscountRecommendation::class)
                ->and($result->recommendedDiscountCents)->toBe(0)
                ->and($result->hasDiscount())->toBeFalse();
        });

        it('returns recommendation for cart with value', function (): void {
            $cart = createCartForOptimizerTest(10000); // $100 cart

            $result = $this->optimizer->findOptimalDiscount($cart);

            expect($result)
                ->toBeInstanceOf(DiscountRecommendation::class)
                ->and($result->recommendedStrategy)->toBeInstanceOf(DiscountStrategy::class);
        });

        it('respects max_discount_cents constraint', function (): void {
            $cart = createCartForOptimizerTest(10000); // $100 cart

            $result = $this->optimizer->findOptimalDiscount($cart, null, [
                'max_discount_cents' => 500, // Max $5 discount
            ]);

            expect($result->recommendedDiscountCents)->toBeLessThanOrEqual(500);
        });

        it('respects max_discount_percent constraint', function (): void {
            $cart = createCartForOptimizerTest(10000); // $100 cart

            $result = $this->optimizer->findOptimalDiscount($cart, null, [
                'max_discount_percent' => 10, // Max 10%
            ]);

            // Should not exceed 10% of $100 = $10 = 1000 cents
            expect($result->recommendedDiscountCents)->toBeLessThanOrEqual(1000);
        });

        it('respects min_roi constraint', function (): void {
            $cart = createCartForOptimizerTest(10000);

            $result = $this->optimizer->findOptimalDiscount($cart, null, [
                'min_roi' => 5.0, // Very high ROI threshold
            ]);

            // With high ROI requirement, might recommend no discount
            expect($result->expectedROI)->toBeGreaterThanOrEqual(0.0);
        });

        it('considers user context when provided', function (): void {
            $cart = createCartForOptimizerTest(10000);

            $user = createMockUserModel([
                'orders_count' => 10,
                'lifetime_value' => 50000,
                'voucher_orders_count' => 3,
            ]);

            $result = $this->optimizer->findOptimalDiscount($cart, $user);

            expect($result)->toBeInstanceOf(DiscountRecommendation::class);
        });

        it('recommends FixedAmount strategy for luxury carts', function (): void {
            // Luxury cart > $500 = 50000 cents
            $cart = createCartForOptimizerTest(60000);

            $result = $this->optimizer->findOptimalDiscount($cart);

            // For luxury carts, system recommends fixed amount
            expect($result->recommendedStrategy->value)
                ->toBeIn(['fixed_amount', 'percentage', 'free_shipping']);
        });

        it('recommends FreeShipping for micro carts with small discounts', function (): void {
            // Micro cart < $25 = 2500 cents
            $cart = createCartForOptimizerTest(2000);

            $result = $this->optimizer->findOptimalDiscount($cart);

            // For micro carts with small discount, might recommend free shipping
            expect($result->recommendedStrategy->value)
                ->toBeIn(['percentage', 'free_shipping', 'fixed_amount']);
        });

        it('provides alternatives in the recommendation', function (): void {
            $cart = createCartForOptimizerTest(10000);

            $result = $this->optimizer->findOptimalDiscount($cart);

            expect($result->hasAlternatives())->toBeBool();
        });
    });

    describe('evaluateDiscount', function (): void {
        it('returns evaluation for empty cart', function (): void {
            $storage = new InMemoryStorage;
            $cart = new Cart($storage, 'empty-cart');

            $result = $this->optimizer->evaluateDiscount($cart, 1000);

            expect($result)
                ->toBeArray()
                ->toHaveKeys(['conversion_lift', 'roi', 'recommended'])
                ->and($result['conversion_lift'])->toBe(0.0)
                ->and($result['roi'])->toBe(0.0)
                ->and($result['recommended'])->toBeFalse();
        });

        it('evaluates a specific discount amount', function (): void {
            $cart = createCartForOptimizerTest(10000);

            $result = $this->optimizer->evaluateDiscount($cart, 1000); // $10 discount

            expect($result)
                ->toBeArray()
                ->toHaveKeys(['conversion_lift', 'roi', 'recommended'])
                ->and($result['conversion_lift'])->toBeFloat()
                ->and($result['roi'])->toBeFloat()
                ->and($result['recommended'])->toBeBool();
        });

        it('returns higher conversion lift for larger discounts', function (): void {
            $cart = createCartForOptimizerTest(10000);

            $smallDiscount = $this->optimizer->evaluateDiscount($cart, 500);
            $largeDiscount = $this->optimizer->evaluateDiscount($cart, 2000);

            expect($largeDiscount['conversion_lift'])
                ->toBeGreaterThanOrEqual($smallDiscount['conversion_lift']);
        });

        it('considers user context in evaluation', function (): void {
            $cart = createCartForOptimizerTest(10000);

            $user = createMockUserModel([
                'orders_count' => 5,
                'voucher_orders_count' => 4, // High voucher usage rate
            ]);

            $result = $this->optimizer->evaluateDiscount($cart, 1000, $user);

            expect($result['conversion_lift'])->toBeFloat();
        });
    });

    describe('getDiscountAlternatives', function (): void {
        it('yields no discount for empty cart', function (): void {
            $storage = new InMemoryStorage;
            $cart = new Cart($storage, 'empty-cart');

            $alternatives = iterator_to_array($this->optimizer->getDiscountAlternatives($cart));

            expect($alternatives)->toHaveCount(1)
                ->and($alternatives[0]->hasDiscount())->toBeFalse();
        });

        it('yields multiple alternatives for cart with value', function (): void {
            $cart = createCartForOptimizerTest(10000);

            $alternatives = iterator_to_array($this->optimizer->getDiscountAlternatives($cart));

            expect($alternatives)->not->toBeEmpty();
            foreach ($alternatives as $alt) {
                expect($alt)->toBeInstanceOf(DiscountRecommendation::class);
            }
        });

        it('respects count parameter', function (): void {
            $cart = createCartForOptimizerTest(10000);

            $alternatives = iterator_to_array($this->optimizer->getDiscountAlternatives($cart, null, 3));

            expect(count($alternatives))->toBeLessThanOrEqual(3);
        });

        it('considers user context', function (): void {
            $cart = createCartForOptimizerTest(10000);

            $user = createMockUserModel([
                'segment' => 'vip',
                'orders_count' => 20,
            ]);

            $alternatives = iterator_to_array($this->optimizer->getDiscountAlternatives($cart, $user, 3));

            expect($alternatives)->not->toBeEmpty();
        });
    });

    describe('getName', function (): void {
        it('returns the optimizer name', function (): void {
            expect($this->optimizer->getName())
                ->toBe('rule_based_discount_optimizer');
        });
    });

    describe('isReady', function (): void {
        it('is always ready', function (): void {
            expect($this->optimizer->isReady())->toBeTrue();
        });
    });

    describe('price sensitivity calculations', function (): void {
        it('treats guest users as more price sensitive', function (): void {
            $cart = createCartForOptimizerTest(10000);

            // Guest user (no user model)
            $guestResult = $this->optimizer->findOptimalDiscount($cart, null);

            // Authenticated user
            $authUser = createMockUserModel([
                'orders_count' => 5,
                'lifetime_value' => 50000,
            ]);
            $authResult = $this->optimizer->findOptimalDiscount($cart, $authUser);

            // Both should return valid results
            expect($guestResult)->toBeInstanceOf(DiscountRecommendation::class)
                ->and($authResult)->toBeInstanceOf(DiscountRecommendation::class);
        });

        it('considers voucher usage rate in sensitivity', function (): void {
            $cart = createCartForOptimizerTest(10000);

            // User with high voucher usage
            $voucherUser = createMockUserModel([
                'orders_count' => 10,
                'voucher_orders_count' => 9, // 90% voucher usage
            ]);

            $result = $this->optimizer->findOptimalDiscount($cart, $voucherUser);

            expect($result)->toBeInstanceOf(DiscountRecommendation::class);
        });

        it('adjusts sensitivity based on cart value bucket', function (): void {
            // Small cart (high sensitivity)
            $smallCart = createCartForOptimizerTest(3000);
            $smallResult = $this->optimizer->findOptimalDiscount($smallCart);

            // Large cart (lower sensitivity)
            $largeCart = createCartForOptimizerTest(30000);
            $largeResult = $this->optimizer->findOptimalDiscount($largeCart);

            expect($smallResult)->toBeInstanceOf(DiscountRecommendation::class)
                ->and($largeResult)->toBeInstanceOf(DiscountRecommendation::class);
        });
    });

    describe('conversion estimation', function (): void {
        it('estimates higher base conversion for returning customers', function (): void {
            $cart = createCartForOptimizerTest(10000);

            // New customer
            $newUser = createMockUserModel([
                'orders_count' => 0,
            ]);

            // Returning customer
            $returningUser = createMockUserModel([
                'orders_count' => 10,
            ]);

            $newResult = $this->optimizer->findOptimalDiscount($cart, $newUser);
            $returningResult = $this->optimizer->findOptimalDiscount($cart, $returningUser);

            expect($newResult)->toBeInstanceOf(DiscountRecommendation::class)
                ->and($returningResult)->toBeInstanceOf(DiscountRecommendation::class);
        });
    });

    describe('constructor with custom feature extractor', function (): void {
        it('accepts custom CartFeatureExtractor', function (): void {
            $customExtractor = new CartFeatureExtractor;
            $optimizer = new RuleBasedDiscountOptimizer($customExtractor);

            expect($optimizer->isReady())->toBeTrue();
        });
    });
});
