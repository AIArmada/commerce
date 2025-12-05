<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Predictors;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\AI\CartFeatureExtractor;
use AIArmada\Vouchers\AI\Contracts\CartFeatureExtractorInterface;
use AIArmada\Vouchers\AI\Contracts\ConversionPredictorInterface;
use AIArmada\Vouchers\AI\ConversionPrediction;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use Illuminate\Database\Eloquent\Model;

/**
 * Rule-based conversion predictor using heuristics.
 *
 * This provides a working implementation that can be swapped out
 * for a real ML model (AWS SageMaker, TensorFlow, etc.) later.
 */
final class RuleBasedConversionPredictor implements ConversionPredictorInterface
{
    public function __construct(
        private readonly CartFeatureExtractorInterface $featureExtractor = new CartFeatureExtractor(),
    ) {}

    public function predictConversion(
        Cart $cart,
        ?VoucherCondition $voucher = null,
        ?Model $user = null,
    ): ConversionPrediction {
        $features = $this->featureExtractor->extract($cart, $user);
        $factors = [];

        // Base probability starts at 50%
        $baseProbability = 0.5;
        $adjustments = 0.0;

        // Cart value impact
        $valueImpact = $this->calculateValueImpact($features);
        $adjustments += $valueImpact['adjustment'];
        $factors['cart_value'] = $valueImpact;

        // User history impact
        $userImpact = $this->calculateUserImpact($features);
        $adjustments += $userImpact['adjustment'];
        $factors['user_history'] = $userImpact;

        // Cart age impact (older carts less likely to convert)
        $ageImpact = $this->calculateAgeImpact($features);
        $adjustments += $ageImpact['adjustment'];
        $factors['cart_age'] = $ageImpact;

        // Time-of-day impact
        $timeImpact = $this->calculateTimeImpact($features);
        $adjustments += $timeImpact['adjustment'];
        $factors['time_of_day'] = $timeImpact;

        // Device impact
        $deviceImpact = $this->calculateDeviceImpact($features);
        $adjustments += $deviceImpact['adjustment'];
        $factors['device'] = $deviceImpact;

        // Calculate probability without voucher
        $withoutVoucher = max(0.0, min(1.0, $baseProbability + $adjustments));

        // Calculate voucher lift
        $voucherLift = $voucher !== null ? $this->calculateVoucherLift($cart, $voucher, $features) : 0.0;
        $withVoucher = max(0.0, min(1.0, $withoutVoucher + $voucherLift));

        // Final probability (with voucher if provided)
        $probability = $voucher !== null ? $withVoucher : $withoutVoucher;

        // Calculate confidence based on data quality
        $confidence = $this->calculateConfidence($features);

        return new ConversionPrediction(
            probability: $probability,
            confidence: $confidence,
            factors: $factors,
            withVoucher: $voucher !== null ? $withVoucher : null,
            withoutVoucher: $withoutVoucher,
            incrementalLift: $voucherLift,
        );
    }

    public function predictConversionBatch(iterable $carts): iterable
    {
        foreach ($carts as $cart) {
            yield $this->predictConversion($cart);
        }
    }

    public function getName(): string
    {
        return 'rule_based_conversion_predictor';
    }

    public function isReady(): bool
    {
        return true;
    }

    /**
     * Calculate cart value impact on conversion.
     *
     * @return array{adjustment: float, reason: string}
     */
    private function calculateValueImpact(array $features): array
    {
        $bucket = $features['cart_value_bucket'] ?? 'micro';

        return match ($bucket) {
            'micro' => ['adjustment' => -0.1, 'reason' => 'Very low cart value reduces commitment'],
            'small' => ['adjustment' => 0.0, 'reason' => 'Normal cart value'],
            'medium' => ['adjustment' => 0.05, 'reason' => 'Medium cart value shows intent'],
            'large' => ['adjustment' => 0.1, 'reason' => 'Large cart value indicates serious buyer'],
            'premium' => ['adjustment' => 0.08, 'reason' => 'Premium cart may have higher abandonment'],
            'luxury' => ['adjustment' => 0.05, 'reason' => 'Luxury purchases often need consideration time'],
            default => ['adjustment' => 0.0, 'reason' => 'Unknown cart value bucket'],
        };
    }

    /**
     * Calculate user history impact on conversion.
     *
     * @return array{adjustment: float, reason: string}
     */
    private function calculateUserImpact(array $features): array
    {
        if (! ($features['is_authenticated'] ?? false)) {
            return ['adjustment' => -0.15, 'reason' => 'Guest users have lower conversion'];
        }

        $orderCount = $features['user_order_count'] ?? 0;

        if ($orderCount === 0) {
            return ['adjustment' => -0.1, 'reason' => 'First-time customer'];
        }

        if ($orderCount >= 5) {
            return ['adjustment' => 0.2, 'reason' => 'Loyal customer with history'];
        }

        if ($orderCount >= 2) {
            return ['adjustment' => 0.1, 'reason' => 'Returning customer'];
        }

        return ['adjustment' => 0.05, 'reason' => 'Customer with one prior order'];
    }

    /**
     * Calculate cart age impact on conversion.
     *
     * @return array{adjustment: float, reason: string}
     */
    private function calculateAgeImpact(array $features): array
    {
        $ageMinutes = $features['cart_age_minutes'] ?? 0;

        if ($ageMinutes < 5) {
            return ['adjustment' => 0.0, 'reason' => 'Fresh cart'];
        }

        if ($ageMinutes < 15) {
            return ['adjustment' => -0.05, 'reason' => 'Cart showing some hesitation'];
        }

        if ($ageMinutes < 60) {
            return ['adjustment' => -0.1, 'reason' => 'Cart aging, user may be distracted'];
        }

        if ($ageMinutes < 1440) { // 24 hours
            return ['adjustment' => -0.2, 'reason' => 'Old cart, high abandonment risk'];
        }

        return ['adjustment' => -0.3, 'reason' => 'Very old cart, likely abandoned'];
    }

    /**
     * Calculate time-of-day impact on conversion.
     *
     * @return array{adjustment: float, reason: string}
     */
    private function calculateTimeImpact(array $features): array
    {
        $hour = $features['hour_of_day'] ?? 12;
        $isWeekend = $features['is_weekend'] ?? false;

        // Weekend adjustments
        if ($isWeekend) {
            if ($hour >= 10 && $hour <= 14) {
                return ['adjustment' => 0.05, 'reason' => 'Weekend morning/early afternoon peak'];
            }

            return ['adjustment' => 0.0, 'reason' => 'Weekend'];
        }

        // Weekday adjustments
        if ($hour >= 11 && $hour <= 13) {
            return ['adjustment' => 0.05, 'reason' => 'Lunch break shopping peak'];
        }

        if ($hour >= 19 && $hour <= 22) {
            return ['adjustment' => 0.1, 'reason' => 'Evening shopping peak'];
        }

        if ($hour >= 0 && $hour <= 5) {
            return ['adjustment' => -0.05, 'reason' => 'Late night, impulsive but low commitment'];
        }

        return ['adjustment' => 0.0, 'reason' => 'Normal business hours'];
    }

    /**
     * Calculate device impact on conversion.
     *
     * @return array{adjustment: float, reason: string}
     */
    private function calculateDeviceImpact(array $features): array
    {
        $deviceType = $features['device_type'] ?? 'desktop';

        return match ($deviceType) {
            'desktop' => ['adjustment' => 0.05, 'reason' => 'Desktop users have higher conversion'],
            'tablet' => ['adjustment' => 0.0, 'reason' => 'Tablet users moderate conversion'],
            'mobile' => ['adjustment' => -0.1, 'reason' => 'Mobile users have lower conversion'],
            default => ['adjustment' => 0.0, 'reason' => 'Unknown device'],
        };
    }

    /**
     * Calculate the lift a voucher provides to conversion.
     *
     * @param array<string, mixed> $features
     */
    private function calculateVoucherLift(Cart $cart, VoucherCondition $voucher, array $features): float
    {
        // Get discount from the voucher data
        $cartValue = $features['cart_value_cents'] ?? 0;
        $voucherData = $voucher->getVoucher();
        $discountValue = $voucherData->value;

        if ($cartValue === 0) {
            return 0.0;
        }

        $discountPercent = ($discountValue / $cartValue) * 100;

        // Discount sensitivity based on cart value
        $bucket = $features['cart_value_bucket'] ?? 'micro';
        $sensitivity = match ($bucket) {
            'micro' => 1.5,   // Small carts very sensitive to discounts
            'small' => 1.3,
            'medium' => 1.0,
            'large' => 0.8,
            'premium' => 0.6,
            'luxury' => 0.4,  // Large carts less sensitive
            default => 1.0,
        };

        // User-based adjustment
        $voucherUsageRate = $features['voucher_usage_rate'] ?? 0.0;
        $userAdjustment = $voucherUsageRate > 0.5 ? 1.2 : 1.0; // Frequent voucher users more responsive

        // Base lift calculation (roughly 1% lift per 1% discount, scaled)
        $baseLift = ($discountPercent / 100) * 0.3 * $sensitivity * $userAdjustment;

        // Cap the maximum lift
        return min(0.4, $baseLift);
    }

    /**
     * Calculate confidence based on available data.
     */
    private function calculateConfidence(array $features): float
    {
        $confidence = 0.5; // Base confidence for rule-based system

        // Authenticated users provide more confidence
        if ($features['is_authenticated'] ?? false) {
            $confidence += 0.15;
        }

        // User history increases confidence
        $orderCount = $features['user_order_count'] ?? 0;
        if ($orderCount > 0) {
            $confidence += min(0.15, $orderCount * 0.03);
        }

        // Cart data quality
        if (($features['cart_age_minutes'] ?? 0) > 0) {
            $confidence += 0.05;
        }

        // Session data
        if (($features['pages_viewed'] ?? 0) > 0) {
            $confidence += 0.05;
        }

        return min(0.85, $confidence); // Cap at 85% for rule-based
    }
}
