<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Predictors;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\AI\AbandonmentRisk;
use AIArmada\Vouchers\AI\CartFeatureExtractor;
use AIArmada\Vouchers\AI\Contracts\AbandonmentPredictorInterface;
use AIArmada\Vouchers\AI\Contracts\CartFeatureExtractorInterface;
use AIArmada\Vouchers\AI\Enums\AbandonmentRiskLevel;
use AIArmada\Vouchers\AI\Enums\InterventionType;
use Illuminate\Database\Eloquent\Model;

/**
 * Rule-based abandonment predictor using heuristics.
 *
 * This provides a working implementation that can be swapped out
 * for a real ML model (AWS SageMaker, TensorFlow, etc.) later.
 */
final class RuleBasedAbandonmentPredictor implements AbandonmentPredictorInterface
{
    public function __construct(
        private readonly CartFeatureExtractorInterface $featureExtractor = new CartFeatureExtractor(),
    ) {}

    public function predictAbandonment(
        Cart $cart,
        ?Model $user = null,
    ): AbandonmentRisk {
        $features = $this->featureExtractor->extract($cart, $user);
        $riskFactors = [];
        $riskScore = 0.0;

        // Cart age is primary abandonment indicator
        $ageRisk = $this->calculateAgeRisk($features);
        $riskScore += $ageRisk['score'];
        if ($ageRisk['score'] > 0) {
            $riskFactors['cart_age'] = $ageRisk;
        }

        // Guest users abandon more
        $guestRisk = $this->calculateGuestRisk($features);
        $riskScore += $guestRisk['score'];
        if ($guestRisk['score'] > 0) {
            $riskFactors['guest_user'] = $guestRisk;
        }

        // Price sensitivity (high-value carts have different patterns)
        $priceRisk = $this->calculatePriceRisk($features);
        $riskScore += $priceRisk['score'];
        if ($priceRisk['score'] > 0) {
            $riskFactors['price_sensitivity'] = $priceRisk;
        }

        // Device-based risk
        $deviceRisk = $this->calculateDeviceRisk($features);
        $riskScore += $deviceRisk['score'];
        if ($deviceRisk['score'] > 0) {
            $riskFactors['device_type'] = $deviceRisk;
        }

        // Time-based risk
        $timeRisk = $this->calculateTimeRisk($features);
        $riskScore += $timeRisk['score'];
        if ($timeRisk['score'] > 0) {
            $riskFactors['time_pattern'] = $timeRisk;
        }

        // User behavior history
        $behaviorRisk = $this->calculateBehaviorRisk($features);
        $riskScore += $behaviorRisk['score'];
        if ($behaviorRisk['score'] > 0) {
            $riskFactors['behavior'] = $behaviorRisk;
        }

        // Normalize score to 0-1
        $normalizedScore = min(1.0, max(0.0, $riskScore));
        $riskLevel = AbandonmentRiskLevel::fromScore($normalizedScore);

        // Determine intervention
        $intervention = $this->recommendIntervention($normalizedScore, $features, $riskFactors);

        // Predict abandonment time
        $predictedTime = $this->predictAbandonmentTime($normalizedScore, $features);

        return new AbandonmentRisk(
            riskScore: $normalizedScore,
            riskLevel: $riskLevel,
            riskFactors: $riskFactors,
            predictedAbandonmentTime: $predictedTime,
            suggestedIntervention: $intervention,
        );
    }

    public function predictAbandonmentBatch(iterable $carts): iterable
    {
        foreach ($carts as $cart) {
            yield $this->predictAbandonment($cart);
        }
    }

    public function getHighRiskCarts(iterable $carts, float $threshold = 0.6): iterable
    {
        foreach ($carts as $cart) {
            $risk = $this->predictAbandonment($cart);

            if ($risk->riskScore >= $threshold) {
                yield ['cart' => $cart, 'risk' => $risk];
            }
        }
    }

    public function getName(): string
    {
        return 'rule_based_abandonment_predictor';
    }

    public function isReady(): bool
    {
        return true;
    }

    /**
     * Calculate risk from cart age.
     *
     * @return array{score: float, reason: string, minutes: int}
     */
    private function calculateAgeRisk(array $features): array
    {
        $ageMinutes = $features['cart_age_minutes'] ?? 0;

        if ($ageMinutes < 5) {
            return ['score' => 0.0, 'reason' => 'Fresh cart', 'minutes' => $ageMinutes];
        }

        if ($ageMinutes < 15) {
            return ['score' => 0.1, 'reason' => 'Cart showing hesitation', 'minutes' => $ageMinutes];
        }

        if ($ageMinutes < 30) {
            return ['score' => 0.2, 'reason' => 'Extended browsing', 'minutes' => $ageMinutes];
        }

        if ($ageMinutes < 60) {
            return ['score' => 0.3, 'reason' => 'Cart aging significantly', 'minutes' => $ageMinutes];
        }

        if ($ageMinutes < 240) { // 4 hours
            return ['score' => 0.4, 'reason' => 'Cart likely to be abandoned', 'minutes' => $ageMinutes];
        }

        return ['score' => 0.5, 'reason' => 'Very old cart, high risk', 'minutes' => $ageMinutes];
    }

    /**
     * Calculate risk from guest status.
     *
     * @return array{score: float, reason: string}
     */
    private function calculateGuestRisk(array $features): array
    {
        if ($features['is_authenticated'] ?? false) {
            $orderCount = $features['user_order_count'] ?? 0;

            if ($orderCount > 0) {
                return ['score' => -0.1, 'reason' => 'Returning customer']; // Negative = reduces risk
            }

            return ['score' => 0.0, 'reason' => 'Authenticated user'];
        }

        return ['score' => 0.15, 'reason' => 'Guest users have higher abandonment'];
    }

    /**
     * Calculate risk from price sensitivity.
     *
     * @return array{score: float, reason: string, bucket: string}
     */
    private function calculatePriceRisk(array $features): array
    {
        $bucket = $features['cart_value_bucket'] ?? 'micro';
        $hasDiscount = ($features['total_discount_cents'] ?? 0) > 0;

        // High-value carts without discount have higher abandonment
        if (in_array($bucket, ['premium', 'luxury']) && ! $hasDiscount) {
            return [
                'score' => 0.15,
                'reason' => 'High-value cart without discount',
                'bucket' => $bucket,
            ];
        }

        // Micro carts often abandoned (not worth checkout effort)
        if ($bucket === 'micro') {
            return [
                'score' => 0.1,
                'reason' => 'Very low value may not justify checkout',
                'bucket' => $bucket,
            ];
        }

        return ['score' => 0.0, 'reason' => 'Normal price range', 'bucket' => $bucket];
    }

    /**
     * Calculate risk from device type.
     *
     * @return array{score: float, reason: string, device: string}
     */
    private function calculateDeviceRisk(array $features): array
    {
        $deviceType = $features['device_type'] ?? 'desktop';

        return match ($deviceType) {
            'mobile' => [
                'score' => 0.1,
                'reason' => 'Mobile has higher abandonment',
                'device' => $deviceType,
            ],
            'tablet' => [
                'score' => 0.05,
                'reason' => 'Tablet moderate risk',
                'device' => $deviceType,
            ],
            default => [
                'score' => 0.0,
                'reason' => 'Desktop has lower abandonment',
                'device' => $deviceType,
            ],
        };
    }

    /**
     * Calculate risk from time patterns.
     *
     * @return array{score: float, reason: string}
     */
    private function calculateTimeRisk(array $features): array
    {
        $hour = $features['hour_of_day'] ?? 12;
        $isEvening = $features['is_evening'] ?? false;
        $isWeekend = $features['is_weekend'] ?? false;

        // Late night shopping often abandoned
        if ($hour >= 0 && $hour <= 5) {
            return ['score' => 0.1, 'reason' => 'Late night impulse shopping'];
        }

        // End of day fatigue
        if ($hour >= 23) {
            return ['score' => 0.08, 'reason' => 'End of day fatigue'];
        }

        // Monday mornings have higher abandonment
        if (! $isWeekend && now()->dayOfWeek === 1 && $hour < 12) {
            return ['score' => 0.05, 'reason' => 'Monday morning distraction'];
        }

        return ['score' => 0.0, 'reason' => 'Normal shopping time'];
    }

    /**
     * Calculate risk from user behavior history.
     *
     * @return array{score: float, reason: string}
     */
    private function calculateBehaviorRisk(array $features): array
    {
        $voucherRate = $features['voucher_usage_rate'] ?? 0.0;
        $refundRate = $features['user_refund_rate'] ?? 0.0;

        // High voucher dependency may wait for coupon
        if ($voucherRate > 0.7) {
            return ['score' => 0.1, 'reason' => 'User usually waits for vouchers'];
        }

        // High refund rate indicates price sensitivity
        if ($refundRate > 0.2) {
            return ['score' => 0.05, 'reason' => 'User has high refund rate'];
        }

        return ['score' => 0.0, 'reason' => 'Normal behavior pattern'];
    }

    /**
     * Recommend intervention based on risk.
     */
    private function recommendIntervention(
        float $riskScore,
        array $features,
        array $riskFactors,
    ): InterventionType {
        // Low risk - no intervention needed
        if ($riskScore < 0.3) {
            return InterventionType::None;
        }

        // Check if user is price sensitive
        $isPriceSensitive = ($riskFactors['price_sensitivity']['score'] ?? 0) > 0
            || ($features['voucher_usage_rate'] ?? 0) > 0.5;

        // Medium risk
        if ($riskScore < 0.6) {
            // Price sensitive users get discount offers
            if ($isPriceSensitive) {
                return InterventionType::DiscountOffer;
            }

            return InterventionType::ExitPopup;
        }

        // High risk
        if ($riskScore < 0.8) {
            return InterventionType::DiscountOffer;
        }

        // Critical risk - email recovery
        return InterventionType::RecoveryEmail;
    }

    /**
     * Predict when abandonment will occur.
     */
    private function predictAbandonmentTime(float $riskScore, array $features): ?\Carbon\Carbon
    {
        // Only predict for medium+ risk
        if ($riskScore < 0.3) {
            return null;
        }

        $ageMinutes = $features['cart_age_minutes'] ?? 0;

        // Higher risk = sooner abandonment
        $minutesRemaining = match (true) {
            $riskScore >= 0.8 => 5,
            $riskScore >= 0.6 => 15,
            $riskScore >= 0.4 => 30,
            default => 60,
        };

        return now()->addMinutes($minutesRemaining);
    }
}
