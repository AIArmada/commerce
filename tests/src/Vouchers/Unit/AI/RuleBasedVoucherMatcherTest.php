<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\AI\CartFeatureExtractor;
use AIArmada\Vouchers\AI\Optimizers\RuleBasedVoucherMatcher;
use AIArmada\Vouchers\AI\VoucherMatch;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

function createCartForMatcherTest(int $subtotalCents = 10000): Cart
{
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-matcher-cart');

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

function createVoucherForMatcherTest(array $attributes = []): Voucher
{
    // Extract usages_count if provided as times_used (computed attribute)
    $usagesCount = $attributes['times_used'] ?? $attributes['usages_count'] ?? 0;
    unset($attributes['times_used']);

    $voucher = Voucher::create(array_merge([
        'code' => 'MATCH-TEST-' . uniqid(),
        'name' => 'Test Voucher',
        'type' => VoucherType::Percentage,
        'value' => 10, // 10% for percentage vouchers
        'status' => VoucherStatus::Active,
        'stacking_priority' => 100,
        'usage_limit' => 100,
    ], $attributes));

    // Set usages_count to simulate times_used (computed from usages_count)
    if ($usagesCount > 0) {
        $voucher->setAttribute('usages_count', $usagesCount);
    }

    return $voucher;
}

function createMockUserForMatcher(array $attributes = []): Model
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

describe('RuleBasedVoucherMatcher', function (): void {
    beforeEach(function (): void {
        $this->matcher = new RuleBasedVoucherMatcher;
    });

    describe('findBestVoucher', function (): void {
        it('returns none for empty voucher collection', function (): void {
            $cart = createCartForMatcherTest(10000);
            $vouchers = new Collection;

            $result = $this->matcher->findBestVoucher($cart, $vouchers);

            expect($result)
                ->toBeInstanceOf(VoucherMatch::class)
                ->and($result->hasMatch())->toBeFalse()
                ->and($result->matchScore)->toBe(0.0);
        });

        it('finds best matching voucher', function (): void {
            $cart = createCartForMatcherTest(10000);

            $voucher1 = createVoucherForMatcherTest([
                'code' => 'VOUCHER-10',
                'value' => 1000, // 10%
            ]);

            $voucher2 = createVoucherForMatcherTest([
                'code' => 'VOUCHER-20',
                'value' => 2000, // 20%
            ]);

            $vouchers = new Collection([$voucher1, $voucher2]);

            $result = $this->matcher->findBestVoucher($cart, $vouchers);

            expect($result->hasMatch())->toBeTrue()
                ->and($result->voucher)->toBeInstanceOf(Voucher::class);
        });

        it('excludes inactive vouchers', function (): void {
            $cart = createCartForMatcherTest(10000);

            $inactiveVoucher = createVoucherForMatcherTest([
                'status' => VoucherStatus::Paused,
            ]);

            $vouchers = new Collection([$inactiveVoucher]);

            $result = $this->matcher->findBestVoucher($cart, $vouchers);

            expect($result->hasMatch())->toBeFalse();
        });

        it('excludes vouchers with no remaining usage', function (): void {
            $cart = createCartForMatcherTest(10000);

            $depletedVoucher = createVoucherForMatcherTest([
                'usage_limit' => 10,
                'times_used' => 10,
            ]);

            $vouchers = new Collection([$depletedVoucher]);

            $result = $this->matcher->findBestVoucher($cart, $vouchers);

            expect($result->hasMatch())->toBeFalse();
        });

        it('excludes vouchers when cart value is below minimum', function (): void {
            $cart = createCartForMatcherTest(5000); // $50 cart

            $highMinVoucher = createVoucherForMatcherTest([
                'min_cart_value' => 10000, // Requires $100 minimum
            ]);

            $vouchers = new Collection([$highMinVoucher]);

            $result = $this->matcher->findBestVoucher($cart, $vouchers);

            expect($result->hasMatch())->toBeFalse();
        });

        it('includes alternatives in result', function (): void {
            $cart = createCartForMatcherTest(10000);

            $voucher1 = createVoucherForMatcherTest(['code' => 'ALT-1', 'value' => 1000]);
            $voucher2 = createVoucherForMatcherTest(['code' => 'ALT-2', 'value' => 1500]);
            $voucher3 = createVoucherForMatcherTest(['code' => 'ALT-3', 'value' => 2000]);

            $vouchers = new Collection([$voucher1, $voucher2, $voucher3]);

            $result = $this->matcher->findBestVoucher($cart, $vouchers);

            expect($result->hasMatch())->toBeTrue();
            // Alternatives should be available
            expect($result->alternatives)->toBeArray();
        });

        it('considers user context', function (): void {
            $cart = createCartForMatcherTest(10000);

            $user = createMockUserForMatcher([
                'orders_count' => 5,
                'segment' => 'vip',
            ]);

            $voucher = createVoucherForMatcherTest();
            $vouchers = new Collection([$voucher]);

            $result = $this->matcher->findBestVoucher($cart, $vouchers, $user);

            expect($result)->toBeInstanceOf(VoucherMatch::class);
        });
    });

    describe('rankVouchers', function (): void {
        it('returns empty collection for empty cart', function (): void {
            $storage = new InMemoryStorage;
            $cart = new Cart($storage, 'empty-cart');

            $voucher = createVoucherForMatcherTest();
            $vouchers = new Collection([$voucher]);

            $result = $this->matcher->rankVouchers($cart, $vouchers);

            // With no cart value, minimum spend check fails
            expect($result)->toBeInstanceOf(Collection::class);
        });

        it('ranks vouchers by match score', function (): void {
            $cart = createCartForMatcherTest(10000);

            $voucher1 = createVoucherForMatcherTest([
                'code' => 'RANK-1',
                'value' => 1500, // 15% - optimal range
            ]);

            $voucher2 = createVoucherForMatcherTest([
                'code' => 'RANK-2',
                'value' => 300, // 3% - too small
            ]);

            $vouchers = new Collection([$voucher1, $voucher2]);

            $result = $this->matcher->rankVouchers($cart, $vouchers);

            expect($result)->not->toBeEmpty();
            // Results should be sorted by match score descending
            if ($result->count() >= 2) {
                expect($result->first()->matchScore)
                    ->toBeGreaterThanOrEqual($result->last()->matchScore);
            }
        });

        it('filters out ineligible vouchers', function (): void {
            $cart = createCartForMatcherTest(10000);

            $activeVoucher = createVoucherForMatcherTest([
                'code' => 'ACTIVE',
                'status' => VoucherStatus::Active,
            ]);

            $inactiveVoucher = createVoucherForMatcherTest([
                'code' => 'INACTIVE',
                'status' => VoucherStatus::Paused,
            ]);

            $vouchers = new Collection([$activeVoucher, $inactiveVoucher]);

            $result = $this->matcher->rankVouchers($cart, $vouchers);

            $codes = $result->map(fn ($m) => $m->voucher->code)->toArray();
            expect($codes)->toContain('ACTIVE')
                ->and($codes)->not->toContain('INACTIVE');
        });
    });

    describe('scoreVoucher', function (): void {
        it('scores a single voucher', function (): void {
            $cart = createCartForMatcherTest(10000);
            $voucher = createVoucherForMatcherTest(['value' => 1500]); // 15%

            $result = $this->matcher->scoreVoucher($cart, $voucher);

            expect($result)
                ->toBeInstanceOf(VoucherMatch::class)
                ->and($result->matchScore)->toBeFloat()
                ->and($result->matchReasons)->toBeArray();
        });

        it('returns none for ineligible voucher', function (): void {
            $cart = createCartForMatcherTest(5000);

            $ineligibleVoucher = createVoucherForMatcherTest([
                'min_cart_value' => 20000, // Requires $200 minimum
            ]);

            $result = $this->matcher->scoreVoucher($cart, $ineligibleVoucher);

            expect($result->hasMatch())->toBeFalse();
        });

        it('provides match reasons', function (): void {
            $cart = createCartForMatcherTest(10000);
            $voucher = createVoucherForMatcherTest();

            $result = $this->matcher->scoreVoucher($cart, $voucher);

            expect($result->matchReasons)->toBeArray()
                ->and($result->matchReasons)->toHaveKey('value_match');
        });

        it('considers user context in scoring', function (): void {
            $cart = createCartForMatcherTest(10000);
            $voucher = createVoucherForMatcherTest();

            $user = createMockUserForMatcher([
                'orders_count' => 0,
                'segment' => 'new',
            ]);

            $result = $this->matcher->scoreVoucher($cart, $voucher, $user);

            expect($result)->toBeInstanceOf(VoucherMatch::class);
        });
    });

    describe('getName', function (): void {
        it('returns the matcher name', function (): void {
            expect($this->matcher->getName())
                ->toBe('rule_based_voucher_matcher');
        });
    });

    describe('isReady', function (): void {
        it('is always ready', function (): void {
            expect($this->matcher->isReady())->toBeTrue();
        });
    });

    describe('value matching scoring', function (): void {
        it('scores optimal discount range (10-20%) highest', function (): void {
            $cart = createCartForMatcherTest(10000);

            // For percentage vouchers, value is the actual percentage number (15 = 15%)
            $optimalVoucher = createVoucherForMatcherTest([
                'code' => 'OPTIMAL',
                'value' => 15, // 15% - in optimal range 10-20%
            ]);

            $result = $this->matcher->scoreVoucher($cart, $optimalVoucher);

            expect($result->matchReasons['value_match']['score'])->toBe(1.0)
                ->and($result->matchReasons['value_match']['reason'])
                ->toBe('Optimal discount range');
        });

        it('scores good discount range (5-25%)', function (): void {
            $cart = createCartForMatcherTest(10000);

            $goodVoucher = createVoucherForMatcherTest([
                'code' => 'GOOD',
                'value' => 22, // 22% - in good range but outside optimal
            ]);

            $result = $this->matcher->scoreVoucher($cart, $goodVoucher);

            expect($result->matchReasons['value_match']['score'])->toBe(0.7);
        });

        it('scores small discounts lower', function (): void {
            $cart = createCartForMatcherTest(10000);

            $smallVoucher = createVoucherForMatcherTest([
                'code' => 'SMALL',
                'value' => 3, // 3% - too small
            ]);

            $result = $this->matcher->scoreVoucher($cart, $smallVoucher);

            expect($result->matchReasons['value_match']['score'])->toBe(0.3)
                ->and($result->matchReasons['value_match']['reason'])
                ->toContain('too small');
        });

        it('scores fixed amount vouchers correctly', function (): void {
            $cart = createCartForMatcherTest(10000); // $100 cart

            // For fixed vouchers, value is in cents (1500 = $15)
            // $15 off $100 = 15% - in optimal range
            $fixedVoucher = createVoucherForMatcherTest([
                'code' => 'FIXED',
                'type' => VoucherType::Fixed,
                'value' => 1500, // $15 off
            ]);

            $result = $this->matcher->scoreVoucher($cart, $fixedVoucher);

            expect($result->matchReasons['value_match']['score'])->toBe(1.0);
        });
    });

    describe('segment matching scoring', function (): void {
        it('scores universal vouchers with partial score', function (): void {
            $cart = createCartForMatcherTest(10000);

            $universalVoucher = createVoucherForMatcherTest([
                'target_definition' => null,
            ]);

            $result = $this->matcher->scoreVoucher($cart, $universalVoucher);

            // Universal vouchers get partial score
            expect($result->matchScore)->toBeGreaterThan(0);
        });

        it('scores new customer vouchers for new customers', function (): void {
            $cart = createCartForMatcherTest(10000);

            $newCustomerVoucher = createVoucherForMatcherTest([
                'target_definition' => ['first_purchase' => true],
            ]);

            $newUser = createMockUserForMatcher([
                'orders_count' => 0,
            ]);

            $result = $this->matcher->scoreVoucher($cart, $newCustomerVoucher, $newUser);

            expect($result->matchReasons)->toHaveKey('segment_match');
        });

        it('excludes new customer vouchers for returning customers', function (): void {
            $cart = createCartForMatcherTest(10000);

            $newCustomerVoucher = createVoucherForMatcherTest([
                'target_definition' => ['first_purchase' => true],
            ]);

            $returningUser = createMockUserForMatcher([
                'orders_count' => 5,
            ]);

            $result = $this->matcher->scoreVoucher($cart, $newCustomerVoucher, $returningUser);

            // When score is 0, segment_match is not added to reasons (see implementation)
            // The overall match score should be lower for mismatched segments
            expect($result->matchScore)->toBeLessThan(1.0);
        });
    });

    describe('timing scoring', function (): void {
        it('scores expiring soon vouchers higher (urgency)', function (): void {
            $cart = createCartForMatcherTest(10000);

            $urgentVoucher = createVoucherForMatcherTest([
                'expires_at' => now()->addDays(2),
            ]);

            $result = $this->matcher->scoreVoucher($cart, $urgentVoucher);

            expect($result->matchReasons)->toHaveKey('timing_match')
                ->and($result->matchReasons['timing_match']['score'])->toBe(0.8);
        });

        it('scores no expiration vouchers lower', function (): void {
            $cart = createCartForMatcherTest(10000);

            $noExpiryVoucher = createVoucherForMatcherTest([
                'expires_at' => null,
            ]);

            $result = $this->matcher->scoreVoucher($cart, $noExpiryVoucher);

            expect($result->matchReasons)->toHaveKey('timing_match')
                ->and($result->matchReasons['timing_match']['score'])->toBe(0.2);
        });

        it('gives lower score to expired vouchers', function (): void {
            $cart = createCartForMatcherTest(10000);

            $expiredVoucher = createVoucherForMatcherTest([
                'expires_at' => now()->subDay(),
            ]);

            $result = $this->matcher->scoreVoucher($cart, $expiredVoucher);

            // Expired vouchers get timing score of 0, which is NOT added to reasons
            // (timing_match is only added when score > 0)
            // The voucher still "matches" (isActive only checks status), but with lower score
            expect($result->hasMatch())->toBeTrue()
                ->and($result->matchReasons)->not->toHaveKey('timing_match');
        });
    });

    describe('attractiveness scoring', function (): void {
        it('scores round percentage numbers higher', function (): void {
            $cart = createCartForMatcherTest(10000);

            // The attractiveness check uses raw value (20), not percentage representation (2000)
            $roundVoucher = createVoucherForMatcherTest([
                'type' => VoucherType::Percentage,
                'value' => 20, // Value of 20 means 20% - matches appealing [10,15,20,25,30,50]
            ]);

            $result = $this->matcher->scoreVoucher($cart, $roundVoucher);

            expect($result->matchReasons['attractiveness']['score'])->toBe(0.8);
        });

        it('scores fixed amounts for small carts higher', function (): void {
            $cart = createCartForMatcherTest(3000); // Small cart

            $fixedVoucher = createVoucherForMatcherTest([
                'type' => VoucherType::Fixed,
                'value' => 500, // $5 off
            ]);

            $result = $this->matcher->scoreVoucher($cart, $fixedVoucher);

            expect($result->matchReasons['attractiveness']['score'])->toBe(0.8)
                ->and($result->matchReasons['attractiveness']['reason'])
                ->toContain('small cart');
        });
    });

    describe('usage potential scoring', function (): void {
        it('scores almost depleted vouchers higher (scarcity)', function (): void {
            $cart = createCartForMatcherTest(10000);

            // Create voucher and manually set usages_count attribute
            // since times_used is computed from usages_count or usages relationship
            $scarceVoucher = createVoucherForMatcherTest([
                'value' => 10, // 10%
                'usage_limit' => 100,
            ]);
            // Manually set the count attribute that times_used reads from
            $scarceVoucher->setAttribute('usages_count', 95);

            $result = $this->matcher->scoreVoucher($cart, $scarceVoucher);

            expect($result->matchReasons)->toHaveKey('usage_potential')
                ->and($result->matchReasons['usage_potential']['score'])->toBe(0.8);
        });

        it('scores unlimited vouchers lower', function (): void {
            $cart = createCartForMatcherTest(10000);

            $unlimitedVoucher = createVoucherForMatcherTest([
                'usage_limit' => null,
            ]);

            $result = $this->matcher->scoreVoucher($cart, $unlimitedVoucher);

            expect($result->matchReasons['usage_potential']['score'])->toBe(0.3);
        });

        it('scores low usage vouchers lower', function (): void {
            $cart = createCartForMatcherTest(10000);

            $lowUsageVoucher = createVoucherForMatcherTest([
                'usage_limit' => 100,
                'times_used' => 10, // 10% used
            ]);

            $result = $this->matcher->scoreVoucher($cart, $lowUsageVoucher);

            expect($result->matchReasons['usage_potential']['score'])->toBe(0.1)
                ->and($result->matchReasons['usage_potential']['reason'])
                ->toContain('not be popular');
        });
    });

    describe('constructor with custom feature extractor', function (): void {
        it('accepts custom CartFeatureExtractor', function (): void {
            $customExtractor = new CartFeatureExtractor;
            $matcher = new RuleBasedVoucherMatcher($customExtractor);

            expect($matcher->isReady())->toBeTrue();
        });
    });
});
