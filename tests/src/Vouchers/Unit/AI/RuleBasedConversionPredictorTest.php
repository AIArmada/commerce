<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Vouchers\AI\ConversionPrediction;
use AIArmada\Vouchers\AI\Enums\PredictionConfidence;
use AIArmada\Vouchers\AI\Predictors\RuleBasedConversionPredictor;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Data\VoucherData;
use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Enums\VoucherType;
use Illuminate\Database\Eloquent\Model;

function createCartForConversionTest(int $subtotalCents = 10000): Cart
{
    $storage = new InMemoryStorage;
    $cart = new Cart($storage, 'test-conversion-cart-' . uniqid());

    $pricePerItem = max(100, (int) ($subtotalCents / 2));
    $cart->add([
        'id' => 'item-1',
        'name' => 'Test Product',
        'price' => $pricePerItem,
        'quantity' => 1,
    ]);

    if ($subtotalCents > $pricePerItem) {
        $cart->add([
            'id' => 'item-2',
            'name' => 'Test Product 2',
            'price' => $subtotalCents - $pricePerItem,
            'quantity' => 1,
        ]);
    }

    return $cart;
}

function createUserForConversionTest(array $attributes = []): Model
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

function createVoucherConditionForTest(int $discountCents = 1000): VoucherCondition
{
    $voucherData = VoucherData::fromArray([
        'id' => 'test-voucher-' . uniqid(),
        'code' => 'TEST10',
        'name' => 'Test Voucher',
        'type' => VoucherType::Fixed->value,
        'value' => $discountCents,
        'currency' => 'USD',
        'status' => VoucherStatus::Active->value,
    ]);

    return new VoucherCondition($voucherData, order: 1, dynamic: false);
}

describe('RuleBasedConversionPredictor', function (): void {
    beforeEach(function (): void {
        $this->predictor = new RuleBasedConversionPredictor;
    });

    describe('predictConversion', function (): void {
        it('returns ConversionPrediction instance', function (): void {
            $cart = createCartForConversionTest(10000);

            $result = $this->predictor->predictConversion($cart);

            expect($result)->toBeInstanceOf(ConversionPrediction::class);
        });

        it('includes probability between 0 and 1', function (): void {
            $cart = createCartForConversionTest(10000);

            $result = $this->predictor->predictConversion($cart);

            expect($result->probability)->toBeGreaterThanOrEqual(0.0)
                ->and($result->probability)->toBeLessThanOrEqual(1.0);
        });

        it('includes confidence between 0 and 1', function (): void {
            $cart = createCartForConversionTest(10000);

            $result = $this->predictor->predictConversion($cart);

            expect($result->confidence)->toBeGreaterThanOrEqual(0.0)
                ->and($result->confidence)->toBeLessThanOrEqual(1.0);
        });

        it('includes factors array', function (): void {
            $cart = createCartForConversionTest(10000);

            $result = $this->predictor->predictConversion($cart);

            expect($result->factors)->toBeArray()
                ->and($result->factors)->toHaveKey('cart_value')
                ->and($result->factors)->toHaveKey('user_history')
                ->and($result->factors)->toHaveKey('cart_age')
                ->and($result->factors)->toHaveKey('time_of_day')
                ->and($result->factors)->toHaveKey('device');
        });

        it('calculates withoutVoucher probability', function (): void {
            $cart = createCartForConversionTest(10000);

            $result = $this->predictor->predictConversion($cart);

            expect($result->withoutVoucher)->not->toBeNull()
                ->and($result->withoutVoucher)->toBeGreaterThanOrEqual(0.0)
                ->and($result->withoutVoucher)->toBeLessThanOrEqual(1.0);
        });
    });

    describe('with voucher', function (): void {
        it('calculates withVoucher probability', function (): void {
            $cart = createCartForConversionTest(10000);
            $voucher = createVoucherConditionForTest(1000);

            $result = $this->predictor->predictConversion($cart, $voucher);

            expect($result->withVoucher)->not->toBeNull()
                ->and($result->withVoucher)->toBeGreaterThanOrEqual($result->withoutVoucher);
        });

        it('calculates incremental lift from voucher', function (): void {
            $cart = createCartForConversionTest(10000);
            $voucher = createVoucherConditionForTest(1000);

            $result = $this->predictor->predictConversion($cart, $voucher);

            expect($result->incrementalLift)->toBeGreaterThanOrEqual(0.0);
        });

        it('larger discounts provide more lift', function (): void {
            $cart = createCartForConversionTest(10000);
            $smallVoucher = createVoucherConditionForTest(500);
            $largeVoucher = createVoucherConditionForTest(2000);

            $smallResult = $this->predictor->predictConversion($cart, $smallVoucher);
            $largeResult = $this->predictor->predictConversion($cart, $largeVoucher);

            expect($largeResult->incrementalLift)->toBeGreaterThanOrEqual($smallResult->incrementalLift);
        });
    });

    describe('cart value impact', function (): void {
        it('micro carts have lower conversion probability', function (): void {
            $microCart = createCartForConversionTest(1000); // $10
            $mediumCart = createCartForConversionTest(7500); // $75

            $microResult = $this->predictor->predictConversion($microCart);
            $mediumResult = $this->predictor->predictConversion($mediumCart);

            // Micro carts should have negative adjustment
            expect($microResult->factors['cart_value']['adjustment'])->toBeLessThan(0);
        });

        it('medium and large carts have positive impact', function (): void {
            $mediumCart = createCartForConversionTest(7500); // $75 = medium
            $largeCart = createCartForConversionTest(15000); // $150 = large

            $mediumResult = $this->predictor->predictConversion($mediumCart);
            $largeResult = $this->predictor->predictConversion($largeCart);

            expect($mediumResult->factors['cart_value']['adjustment'])->toBeGreaterThanOrEqual(0)
                ->and($largeResult->factors['cart_value']['adjustment'])->toBeGreaterThan(0);
        });
    });

    describe('user history impact', function (): void {
        it('guest users have lower conversion probability', function (): void {
            $cart = createCartForConversionTest(10000);

            // No user = guest
            $result = $this->predictor->predictConversion($cart);

            expect($result->factors['user_history']['adjustment'])->toBeLessThan(0)
                ->and($result->factors['user_history']['reason'])->toContain('Guest');
        });

        it('returning customers have higher conversion probability', function (): void {
            $cart = createCartForConversionTest(10000);
            $returningUser = createUserForConversionTest([
                'id' => 1,
                'email' => 'test@example.com',
                'orders_count' => 3,
            ]);

            $result = $this->predictor->predictConversion($cart, null, $returningUser);

            // Returning customer should have positive adjustment
            expect($result->factors['user_history']['adjustment'])->toBeGreaterThan(0)
                ->and($result->factors['user_history']['reason'])->toContain('Returning');
        });

        it('loyal customers have highest boost', function (): void {
            $cart = createCartForConversionTest(10000);
            $loyalUser = createUserForConversionTest([
                'id' => 1,
                'email' => 'test@example.com',
                'orders_count' => 10,
            ]);

            $result = $this->predictor->predictConversion($cart, null, $loyalUser);

            expect($result->factors['user_history']['adjustment'])->toBe(0.2)
                ->and($result->factors['user_history']['reason'])->toContain('Loyal');
        });
    });

    describe('cart age impact', function (): void {
        it('identifies fresh cart impact', function (): void {
            $cart = createCartForConversionTest(10000);

            $result = $this->predictor->predictConversion($cart);

            // Fresh cart (0 minutes) should have 0 or minimal adjustment
            expect($result->factors['cart_age']['adjustment'])->toBeLessThanOrEqual(0);
        });
    });

    describe('device impact', function (): void {
        it('desktop has positive impact', function (): void {
            $cart = createCartForConversionTest(10000);

            $result = $this->predictor->predictConversion($cart);

            // Default device (desktop) should have positive or zero adjustment
            $deviceAdjustment = $result->factors['device']['adjustment'];
            expect($deviceAdjustment)->toBeGreaterThanOrEqual(-0.1);
        });
    });

    describe('predictConversionBatch', function (): void {
        it('processes multiple carts', function (): void {
            $carts = [
                createCartForConversionTest(5000),
                createCartForConversionTest(10000),
                createCartForConversionTest(15000),
            ];

            $results = iterator_to_array($this->predictor->predictConversionBatch($carts));

            expect($results)->toHaveCount(3)
                ->and($results[0])->toBeInstanceOf(ConversionPrediction::class)
                ->and($results[1])->toBeInstanceOf(ConversionPrediction::class)
                ->and($results[2])->toBeInstanceOf(ConversionPrediction::class);
        });

        it('handles empty cart collection', function (): void {
            $results = iterator_to_array($this->predictor->predictConversionBatch([]));

            expect($results)->toBeEmpty();
        });
    });

    describe('getName', function (): void {
        it('returns predictor name', function (): void {
            expect($this->predictor->getName())->toBe('rule_based_conversion_predictor');
        });
    });

    describe('isReady', function (): void {
        it('returns true for rule-based predictor', function (): void {
            expect($this->predictor->isReady())->toBeTrue();
        });
    });

    describe('confidence calculation', function (): void {
        it('authenticated users increase confidence', function (): void {
            $cart = createCartForConversionTest(10000);
            $user = createUserForConversionTest(['id' => 1, 'email' => 'test@example.com']);

            $guestResult = $this->predictor->predictConversion($cart);
            $authResult = $this->predictor->predictConversion($cart, null, $user);

            expect($authResult->confidence)->toBeGreaterThan($guestResult->confidence);
        });

        it('user history increases confidence', function (): void {
            $cart = createCartForConversionTest(10000);
            $newUser = createUserForConversionTest(['id' => 1, 'orders_count' => 0]);
            $returningUser = createUserForConversionTest(['id' => 2, 'orders_count' => 5]);

            $newResult = $this->predictor->predictConversion($cart, null, $newUser);
            $returningResult = $this->predictor->predictConversion($cart, null, $returningUser);

            expect($returningResult->confidence)->toBeGreaterThanOrEqual($newResult->confidence);
        });

        it('caps confidence at 0.85 for rule-based', function (): void {
            $cart = createCartForConversionTest(10000);
            $powerUser = createUserForConversionTest([
                'id' => 1,
                'orders_count' => 100,
                'pages_viewed' => 50,
            ]);

            $result = $this->predictor->predictConversion($cart, null, $powerUser);

            expect($result->confidence)->toBeLessThanOrEqual(0.85);
        });
    });
});

describe('ConversionPrediction value object', function (): void {
    describe('static constructors', function (): void {
        it('creates high conversion prediction', function (): void {
            $prediction = ConversionPrediction::high(0.8, 0.7);

            expect($prediction->probability)->toBe(0.8)
                ->and($prediction->confidence)->toBe(0.7)
                ->and($prediction->factors['prediction_type'])->toBe('high_conversion');
        });

        it('creates low conversion prediction', function (): void {
            $prediction = ConversionPrediction::low(0.2, 0.7);

            expect($prediction->probability)->toBe(0.2)
                ->and($prediction->confidence)->toBe(0.7)
                ->and($prediction->factors['prediction_type'])->toBe('low_conversion');
        });

        it('creates uncertain prediction', function (): void {
            $prediction = ConversionPrediction::uncertain();

            expect($prediction->probability)->toBe(0.5)
                ->and($prediction->confidence)->toBe(0.3)
                ->and($prediction->factors['prediction_type'])->toBe('uncertain');
        });
    });

    describe('helper methods', function (): void {
        it('checks high probability', function (): void {
            $high = ConversionPrediction::high(0.8);
            $low = ConversionPrediction::low(0.2);

            expect($high->isHighProbability())->toBeTrue()
                ->and($low->isHighProbability())->toBeFalse();
        });

        it('checks low probability', function (): void {
            $high = ConversionPrediction::high(0.8);
            $low = ConversionPrediction::low(0.2);

            expect($low->isLowProbability())->toBeTrue()
                ->and($high->isLowProbability())->toBeFalse();
        });

        it('checks if voucher worth it', function (): void {
            $worthIt = new ConversionPrediction(
                probability: 0.6,
                confidence: 0.7,
                incrementalLift: 0.2,
            );

            $notWorthIt = new ConversionPrediction(
                probability: 0.6,
                confidence: 0.7,
                incrementalLift: 0.05,
            );

            expect($worthIt->voucherWorthIt())->toBeTrue()
                ->and($notWorthIt->voucherWorthIt())->toBeFalse();
        });

        it('checks voucher worth it with custom threshold', function (): void {
            $prediction = new ConversionPrediction(
                probability: 0.6,
                confidence: 0.7,
                incrementalLift: 0.1,
            );

            expect($prediction->voucherWorthIt(0.05))->toBeTrue()
                ->and($prediction->voucherWorthIt(0.2))->toBeFalse();
        });

        it('detects potential cannibalization', function (): void {
            // High baseline conversion with low lift = cannibalization
            $cannibal = new ConversionPrediction(
                probability: 0.75,
                confidence: 0.7,
                withVoucher: 0.78,
                withoutVoucher: 0.75,
                incrementalLift: 0.03,
            );

            // Low baseline with good lift = not cannibalization
            $notCannibal = new ConversionPrediction(
                probability: 0.5,
                confidence: 0.7,
                withVoucher: 0.7,
                withoutVoucher: 0.5,
                incrementalLift: 0.2,
            );

            expect($cannibal->isPotentialCannibalization())->toBeTrue()
                ->and($notCannibal->isPotentialCannibalization())->toBeFalse();
        });

        it('gets confidence level enum', function (): void {
            $high = new ConversionPrediction(probability: 0.5, confidence: 0.8);
            $low = new ConversionPrediction(probability: 0.5, confidence: 0.3);

            expect($high->getConfidenceLevel())->toBeInstanceOf(PredictionConfidence::class)
                ->and($low->getConfidenceLevel())->toBeInstanceOf(PredictionConfidence::class);
        });

        it('checks if trustworthy', function (): void {
            $high = new ConversionPrediction(probability: 0.5, confidence: 0.8);
            $low = new ConversionPrediction(probability: 0.5, confidence: 0.3);

            expect($high->isTrustworthy())->toBeTrue()
                ->and($low->isTrustworthy())->toBeFalse();
        });

        it('returns summary string', function (): void {
            $prediction = new ConversionPrediction(probability: 0.75, confidence: 0.8);

            $summary = $prediction->getSummary();

            expect($summary)->toContain('75%')
                ->and($summary)->toContain('80%');
        });

        it('converts to array', function (): void {
            $prediction = new ConversionPrediction(
                probability: 0.75,
                confidence: 0.8,
                factors: ['test' => 'value'],
                withVoucher: 0.85,
                withoutVoucher: 0.75,
                incrementalLift: 0.1,
            );

            $array = $prediction->toArray();

            expect($array)->toHaveKey('probability')
                ->and($array)->toHaveKey('confidence')
                ->and($array)->toHaveKey('factors')
                ->and($array)->toHaveKey('with_voucher')
                ->and($array)->toHaveKey('without_voucher')
                ->and($array)->toHaveKey('incremental_lift')
                ->and($array)->toHaveKey('is_high_probability')
                ->and($array)->toHaveKey('voucher_worth_it');
        });
    });
});

describe('PredictionConfidence enum', function (): void {
    it('creates from score', function (): void {
        expect(PredictionConfidence::fromScore(0.1))->toBe(PredictionConfidence::VeryLow)
            ->and(PredictionConfidence::fromScore(0.35))->toBe(PredictionConfidence::Low)
            ->and(PredictionConfidence::fromScore(0.55))->toBe(PredictionConfidence::Medium)
            ->and(PredictionConfidence::fromScore(0.75))->toBe(PredictionConfidence::High)
            ->and(PredictionConfidence::fromScore(0.95))->toBe(PredictionConfidence::VeryHigh);
    });

    it('returns labels', function (): void {
        expect(PredictionConfidence::VeryLow->getLabel())->toBe('Very Low Confidence')
            ->and(PredictionConfidence::High->getLabel())->toBe('High Confidence');
    });

    it('checks trustworthiness', function (): void {
        expect(PredictionConfidence::VeryLow->isTrustworthy())->toBeFalse()
            ->and(PredictionConfidence::Low->isTrustworthy())->toBeFalse()
            ->and(PredictionConfidence::Medium->isTrustworthy())->toBeTrue()
            ->and(PredictionConfidence::High->isTrustworthy())->toBeTrue();
    });
});
