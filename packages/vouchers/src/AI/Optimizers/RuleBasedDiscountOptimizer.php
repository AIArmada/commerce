<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Optimizers;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\AI\CartFeatureExtractor;
use AIArmada\Vouchers\AI\Contracts\CartFeatureExtractorInterface;
use AIArmada\Vouchers\AI\Contracts\DiscountOptimizerInterface;
use AIArmada\Vouchers\AI\DiscountRecommendation;
use AIArmada\Vouchers\AI\Enums\DiscountStrategy;
use Illuminate\Database\Eloquent\Model;

/**
 * Rule-based discount optimizer using heuristics.
 *
 * This provides a working implementation that can be swapped out
 * for a real ML model (AWS SageMaker, TensorFlow, etc.) later.
 */
final class RuleBasedDiscountOptimizer implements DiscountOptimizerInterface
{
    /**
     * Default discount percentages to evaluate.
     */
    private const DEFAULT_DISCOUNT_LEVELS = [0, 5, 10, 15, 20, 25, 30];

    /**
     * Maximum discount percentage allowed.
     */
    private const MAX_DISCOUNT_PERCENT = 50;

    /**
     * Minimum ROI required for a discount recommendation.
     */
    private const MIN_ROI_THRESHOLD = 1.0;

    public function __construct(
        private readonly CartFeatureExtractorInterface $featureExtractor = new CartFeatureExtractor,
    ) {}

    public function findOptimalDiscount(
        Cart $cart,
        ?Model $user = null,
        array $constraints = [],
    ): DiscountRecommendation {
        $features = $this->featureExtractor->extract($cart, $user);
        $cartValue = $features['cart_value_cents'] ?? 0;

        if ($cartValue === 0) {
            return DiscountRecommendation::noDiscount();
        }

        // Extract constraints
        $maxDiscount = $constraints['max_discount_cents'] ?? null;
        $maxDiscountPercent = $constraints['max_discount_percent'] ?? self::MAX_DISCOUNT_PERCENT;
        $minROI = $constraints['min_roi'] ?? self::MIN_ROI_THRESHOLD;

        // Determine customer price sensitivity
        $sensitivity = $this->calculatePriceSensitivity($features);

        // Get base conversion probability (without discount)
        $baseConversion = $this->estimateBaseConversion($features);

        // Evaluate different discount levels
        $discountLevels = $this->getDiscountLevels($cartValue, $maxDiscountPercent);
        $evaluations = [];

        foreach ($discountLevels as $percentOff) {
            $discountCents = (int) (($percentOff / 100) * $cartValue);

            // Skip if exceeds max discount
            if ($maxDiscount !== null && $discountCents > $maxDiscount) {
                continue;
            }

            $evaluation = $this->evaluateDiscountLevel(
                cartValue: $cartValue,
                discountPercent: $percentOff,
                discountCents: $discountCents,
                baseConversion: $baseConversion,
                sensitivity: $sensitivity,
            );

            $evaluations[$percentOff] = $evaluation;
        }

        // Find optimal discount (best ROI that meets threshold)
        $optimal = $this->findOptimal($evaluations, $minROI);

        // Build alternatives list
        $alternatives = $this->buildAlternatives($evaluations, $optimal['percent'] ?? 0);

        // Determine strategy
        $strategy = $this->recommendStrategy($features, $optimal);

        return new DiscountRecommendation(
            recommendedDiscountCents: $optimal['discount_cents'] ?? 0,
            recommendedStrategy: $strategy,
            expectedConversionLift: $optimal['conversion_lift'] ?? 0.0,
            expectedMarginImpact: $optimal['margin_impact'] ?? 0.0,
            expectedROI: $optimal['roi'] ?? 0.0,
            alternatives: $alternatives,
        );
    }

    public function evaluateDiscount(
        Cart $cart,
        int $discountCents,
        ?Model $user = null,
    ): array {
        $features = $this->featureExtractor->extract($cart, $user);
        $cartValue = $features['cart_value_cents'] ?? 0;

        if ($cartValue === 0) {
            return [
                'conversion_lift' => 0.0,
                'roi' => 0.0,
                'recommended' => false,
            ];
        }

        $discountPercent = ($discountCents / $cartValue) * 100;
        $sensitivity = $this->calculatePriceSensitivity($features);
        $baseConversion = $this->estimateBaseConversion($features);

        $evaluation = $this->evaluateDiscountLevel(
            cartValue: $cartValue,
            discountPercent: $discountPercent,
            discountCents: $discountCents,
            baseConversion: $baseConversion,
            sensitivity: $sensitivity,
        );

        return [
            'conversion_lift' => $evaluation['conversion_lift'],
            'roi' => $evaluation['roi'],
            'recommended' => $evaluation['roi'] >= self::MIN_ROI_THRESHOLD,
        ];
    }

    public function getDiscountAlternatives(
        Cart $cart,
        ?Model $user = null,
        int $count = 5,
    ): iterable {
        $features = $this->featureExtractor->extract($cart, $user);
        $cartValue = $features['cart_value_cents'] ?? 0;

        if ($cartValue === 0) {
            yield DiscountRecommendation::noDiscount();

            return;
        }

        $sensitivity = $this->calculatePriceSensitivity($features);
        $baseConversion = $this->estimateBaseConversion($features);
        $discountLevels = $this->getDiscountLevels($cartValue, self::MAX_DISCOUNT_PERCENT);

        $yielded = 0;

        foreach ($discountLevels as $percentOff) {
            if ($yielded >= $count) {
                break;
            }

            $discountCents = (int) (($percentOff / 100) * $cartValue);

            $evaluation = $this->evaluateDiscountLevel(
                cartValue: $cartValue,
                discountPercent: $percentOff,
                discountCents: $discountCents,
                baseConversion: $baseConversion,
                sensitivity: $sensitivity,
            );

            yield new DiscountRecommendation(
                recommendedDiscountCents: $discountCents,
                recommendedStrategy: DiscountStrategy::Percentage,
                expectedConversionLift: $evaluation['conversion_lift'],
                expectedMarginImpact: $evaluation['margin_impact'],
                expectedROI: $evaluation['roi'],
            );

            $yielded++;
        }
    }

    public function getName(): string
    {
        return 'rule_based_discount_optimizer';
    }

    public function isReady(): bool
    {
        return true;
    }

    /**
     * Calculate customer price sensitivity.
     */
    private function calculatePriceSensitivity(array $features): float
    {
        $sensitivity = 1.0;

        // Guest users are more price sensitive
        if (! ($features['is_authenticated'] ?? false)) {
            $sensitivity += 0.3;
        }

        // Voucher-heavy users are very price sensitive
        $voucherRate = $features['voucher_usage_rate'] ?? 0.0;
        $sensitivity += $voucherRate * 0.5;

        // Cart value affects sensitivity
        $bucket = $features['cart_value_bucket'] ?? 'medium';
        $sensitivity *= match ($bucket) {
            'micro' => 1.5,
            'small' => 1.3,
            'medium' => 1.0,
            'large' => 0.8,
            'premium' => 0.6,
            'luxury' => 0.4,
            default => 1.0,
        };

        return $sensitivity;
    }

    /**
     * Estimate base conversion probability without discount.
     */
    private function estimateBaseConversion(array $features): float
    {
        $base = 0.5;

        // Authenticated users convert better
        if ($features['is_authenticated'] ?? false) {
            $base += 0.1;
        }

        // Returning customers convert better
        $orderCount = $features['user_order_count'] ?? 0;
        if ($orderCount > 0) {
            $base += min(0.15, $orderCount * 0.03);
        }

        // Cart age reduces conversion
        $ageMinutes = $features['cart_age_minutes'] ?? 0;
        if ($ageMinutes > 30) {
            $base -= min(0.2, $ageMinutes * 0.001);
        }

        return max(0.1, min(0.9, $base));
    }

    /**
     * Get discount levels to evaluate.
     *
     * @return array<int>
     */
    private function getDiscountLevels(int $cartValue, float $maxPercent): array
    {
        $levels = self::DEFAULT_DISCOUNT_LEVELS;

        // Filter out levels above max
        return array_filter($levels, fn ($p) => $p <= $maxPercent);
    }

    /**
     * Evaluate a specific discount level.
     *
     * @return array{percent: float, discount_cents: int, conversion_lift: float, margin_impact: float, expected_value: float, roi: float}
     */
    private function evaluateDiscountLevel(
        int $cartValue,
        float $discountPercent,
        int $discountCents,
        float $baseConversion,
        float $sensitivity,
    ): array {
        // Calculate conversion lift based on discount and sensitivity
        $conversionLift = $this->calculateConversionLift($discountPercent, $sensitivity);
        $newConversion = min(0.95, $baseConversion + $conversionLift);

        // Calculate expected values
        $marginWithoutDiscount = $cartValue * $baseConversion;
        $marginWithDiscount = ($cartValue - $discountCents) * $newConversion;
        $marginImpact = ($marginWithDiscount - $marginWithoutDiscount) / max(1, $marginWithoutDiscount);

        // Calculate incremental revenue
        $incrementalRevenue = $cartValue * $conversionLift;
        $roi = $discountCents > 0 ? $incrementalRevenue / $discountCents : 0.0;

        return [
            'percent' => $discountPercent,
            'discount_cents' => $discountCents,
            'conversion_lift' => $conversionLift,
            'margin_impact' => $marginImpact,
            'expected_value' => $marginWithDiscount,
            'roi' => $roi,
        ];
    }

    /**
     * Calculate conversion lift for a discount.
     */
    private function calculateConversionLift(float $discountPercent, float $sensitivity): float
    {
        if ($discountPercent <= 0) {
            return 0.0;
        }

        // Diminishing returns curve
        // ~5% discount gives ~3% lift, ~20% discount gives ~15% lift (scaled by sensitivity)
        $baseLift = 0.6 * (1 - exp(-0.08 * $discountPercent)) * $sensitivity;

        return min(0.4, $baseLift);
    }

    /**
     * Find the optimal discount from evaluations.
     *
     * @return array<string, mixed>
     */
    private function findOptimal(array $evaluations, float $minROI): array
    {
        $optimal = ['percent' => 0, 'discount_cents' => 0, 'roi' => 0.0, 'conversion_lift' => 0.0, 'margin_impact' => 0.0];

        foreach ($evaluations as $evaluation) {
            // Must meet minimum ROI
            if ($evaluation['roi'] < $minROI) {
                continue;
            }

            // Prefer higher ROI
            if ($evaluation['roi'] > $optimal['roi']) {
                $optimal = $evaluation;
            }
        }

        return $optimal;
    }

    /**
     * Build alternatives list.
     *
     * @param  array<int|float, array<string, mixed>>  $evaluations
     * @return array<int, array<string, mixed>>
     */
    private function buildAlternatives(array $evaluations, float $optimalPercent): array
    {
        $alternatives = [];

        foreach ($evaluations as $percent => $evaluation) {
            // Compare as floats to handle array key being int|string
            if ((float) $percent === $optimalPercent) {
                continue;
            }

            $alternatives[] = [
                'discount_percent' => $percent,
                'discount_cents' => $evaluation['discount_cents'],
                'conversion_lift' => round($evaluation['conversion_lift'], 3),
                'roi' => round($evaluation['roi'], 2),
            ];
        }

        // Sort by ROI descending
        usort($alternatives, fn ($a, $b) => $b['roi'] <=> $a['roi']);

        return array_slice($alternatives, 0, 5);
    }

    /**
     * Recommend discount strategy based on context.
     */
    private function recommendStrategy(array $features, array $optimal): DiscountStrategy
    {
        $bucket = $features['cart_value_bucket'] ?? 'medium';

        // For high-value carts, fixed amount is often better perceived
        if (in_array($bucket, ['large', 'premium', 'luxury'])) {
            return DiscountStrategy::FixedAmount;
        }

        // For low-value carts, free shipping is appealing
        if ($bucket === 'micro' && ($optimal['discount_cents'] ?? 0) < 500) {
            return DiscountStrategy::FreeShipping;
        }

        // Default to percentage
        return DiscountStrategy::Percentage;
    }
}
