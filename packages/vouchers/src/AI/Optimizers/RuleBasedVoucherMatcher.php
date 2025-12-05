<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Optimizers;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\AI\CartFeatureExtractor;
use AIArmada\Vouchers\AI\Contracts\CartFeatureExtractorInterface;
use AIArmada\Vouchers\AI\Contracts\VoucherMatcherInterface;
use AIArmada\Vouchers\AI\VoucherMatch;
use AIArmada\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Rule-based voucher matcher using heuristics.
 *
 * This provides a working implementation that can be swapped out
 * for a real ML model (AWS SageMaker, TensorFlow, etc.) later.
 */
final class RuleBasedVoucherMatcher implements VoucherMatcherInterface
{
    public function __construct(
        private readonly CartFeatureExtractorInterface $featureExtractor = new CartFeatureExtractor(),
    ) {}

    public function findBestVoucher(
        Cart $cart,
        Collection $availableVouchers,
        ?Model $user = null,
    ): VoucherMatch {
        if ($availableVouchers->isEmpty()) {
            return VoucherMatch::none();
        }

        $ranked = $this->rankVouchers($cart, $availableVouchers, $user);

        if ($ranked->isEmpty()) {
            return VoucherMatch::none();
        }

        $best = $ranked->first();

        // Build alternatives from remaining
        $alternatives = $ranked->slice(1, 4)->map(fn (VoucherMatch $m) => [
            'voucher_id' => $m->voucher?->id,
            'voucher_code' => $m->voucher?->code,
            'match_score' => $m->matchScore,
            'reasons' => $m->matchReasons,
        ])->values()->all();

        return new VoucherMatch(
            voucher: $best->voucher,
            matchScore: $best->matchScore,
            matchReasons: $best->matchReasons,
            alternatives: $alternatives,
        );
    }

    public function rankVouchers(
        Cart $cart,
        Collection $availableVouchers,
        ?Model $user = null,
    ): Collection
    {
        $features = $this->featureExtractor->extract($cart, $user);
        $cartValue = $features['cart_value_cents'] ?? 0;

        return $availableVouchers
            ->map(fn ($voucher) => $this->scoreVoucherInternal($voucher, $features, $cartValue))
            ->filter(fn (VoucherMatch $match) => $match->matchScore > 0)
            ->sortByDesc(fn (VoucherMatch $match) => $match->matchScore)
            ->values();
    }

    public function scoreVoucher(
        Cart $cart,
        mixed $voucher,
        ?Model $user = null,
    ): VoucherMatch {
        $features = $this->featureExtractor->extract($cart, $user);
        $cartValue = $features['cart_value_cents'] ?? 0;

        return $this->scoreVoucherInternal($voucher, $features, $cartValue);
    }

    public function getName(): string
    {
        return 'rule_based_voucher_matcher';
    }

    public function isReady(): bool
    {
        return true;
    }

    /**
     * Score a voucher against cart features.
     */
    private function scoreVoucherInternal(
        Voucher $voucher,
        array $features,
        int $cartValue,
    ): VoucherMatch {
        $score = 0.0;
        $reasons = [];

        // Eligibility check (basic)
        if (! $this->isEligible($voucher, $features, $cartValue)) {
            return VoucherMatch::none();
        }

        // Value match scoring
        $valueScore = $this->scoreValueMatch($voucher, $cartValue);
        $score += $valueScore['score'];
        $reasons['value_match'] = $valueScore;

        // User segment match
        $segmentScore = $this->scoreSegmentMatch($voucher, $features);
        $score += $segmentScore['score'];
        if ($segmentScore['score'] > 0) {
            $reasons['segment_match'] = $segmentScore;
        }

        // Timing score
        $timingScore = $this->scoreTimingMatch($voucher);
        $score += $timingScore['score'];
        if ($timingScore['score'] > 0) {
            $reasons['timing_match'] = $timingScore;
        }

        // Discount attractiveness
        $attractivenessScore = $this->scoreAttractiveness($voucher, $cartValue, $features);
        $score += $attractivenessScore['score'];
        $reasons['attractiveness'] = $attractivenessScore;

        // Usage potential
        $usageScore = $this->scoreUsagePotential($voucher);
        $score += $usageScore['score'];
        if ($usageScore['score'] !== 0) {
            $reasons['usage_potential'] = $usageScore;
        }

        // Normalize score to 0-1
        $normalizedScore = min(1.0, max(0.0, $score / 4.0)); // Max raw score ~4

        return new VoucherMatch(
            voucher: $voucher,
            matchScore: $normalizedScore,
            matchReasons: $reasons,
        );
    }

    /**
     * Check basic eligibility.
     */
    private function isEligible(Voucher $voucher, array $features, int $cartValue): bool
    {
        // Check if voucher is active
        if (! $voucher->isActive()) {
            return false;
        }

        // Check usage limits
        if (! $voucher->hasUsageLimitRemaining()) {
            return false;
        }

        // Check minimum spend
        $minSpend = $voucher->min_cart_value ?? 0;
        if ($cartValue < $minSpend) {
            return false;
        }

        return true;
    }

    /**
     * Score how well voucher value matches cart value.
     *
     * @return array{score: float, reason: string}
     */
    private function scoreValueMatch(Voucher $voucher, int $cartValue): array
    {
        $voucherValue = $voucher->value ?? 0;
        $type = $voucher->type instanceof \AIArmada\Vouchers\Enums\VoucherType ? $voucher->type->value : 'percentage';

        if ($cartValue === 0) {
            return ['score' => 0.0, 'reason' => 'Empty cart'];
        }

        // For percentage vouchers
        if ($type === 'percentage') {
            $discount = (int) (($voucherValue / 100) * $cartValue);
            $discountPercent = $voucherValue;
        } else {
            // Fixed amount
            $discount = $voucherValue;
            $discountPercent = ($voucherValue / $cartValue) * 100;
        }

        // Optimal discount is 10-20%
        if ($discountPercent >= 10 && $discountPercent <= 20) {
            return ['score' => 1.0, 'reason' => 'Optimal discount range'];
        }

        // Good discount 5-25%
        if ($discountPercent >= 5 && $discountPercent <= 25) {
            return ['score' => 0.7, 'reason' => 'Good discount range'];
        }

        // Too small (< 5%)
        if ($discountPercent < 5) {
            return ['score' => 0.3, 'reason' => 'Discount may be too small to motivate'];
        }

        // Large discount (> 25%)
        return ['score' => 0.5, 'reason' => 'Large discount, margin concern'];
    }

    /**
     * Score user segment match.
     *
     * @return array{score: float, reason: string}
     */
    private function scoreSegmentMatch(Voucher $voucher, array $features): array
    {
        $targetDefinition = $voucher->target_definition ?? [];

        if (empty($targetDefinition)) {
            return ['score' => 0.5, 'reason' => 'No targeting, universal voucher'];
        }

        $userSegment = $features['user_segment'] ?? 'unknown';
        $isNewCustomer = $features['is_new_customer'] ?? true;

        // Check if voucher targets new customers
        if (isset($targetDefinition['first_purchase'])) {
            if ($targetDefinition['first_purchase'] === true && $isNewCustomer) {
                return ['score' => 1.0, 'reason' => 'Matches new customer target'];
            }
            if ($targetDefinition['first_purchase'] === true && ! $isNewCustomer) {
                return ['score' => 0.0, 'reason' => 'New customer only voucher'];
            }
        }

        // Check segment match
        if (isset($targetDefinition['user_segment'])) {
            $targetSegments = (array) $targetDefinition['user_segment'];
            if (in_array($userSegment, $targetSegments)) {
                return ['score' => 1.0, 'reason' => 'Matches user segment'];
            }

            return ['score' => 0.2, 'reason' => 'Segment mismatch'];
        }

        return ['score' => 0.5, 'reason' => 'Partial targeting match'];
    }

    /**
     * Score timing match (urgency, expiration).
     *
     * @return array{score: float, reason: string}
     */
    private function scoreTimingMatch(Voucher $voucher): array
    {
        $expiresAt = $voucher->expires_at;

        if ($expiresAt === null) {
            return ['score' => 0.2, 'reason' => 'No expiration'];
        }

        $daysUntilExpiry = now()->diffInDays($expiresAt, false);

        // Expiring soon creates urgency
        if ($daysUntilExpiry >= 0 && $daysUntilExpiry <= 3) {
            return ['score' => 0.8, 'reason' => 'Expires soon, creates urgency'];
        }

        if ($daysUntilExpiry >= 0 && $daysUntilExpiry <= 7) {
            return ['score' => 0.5, 'reason' => 'Expires within a week'];
        }

        if ($daysUntilExpiry < 0) {
            return ['score' => 0.0, 'reason' => 'Already expired'];
        }

        return ['score' => 0.3, 'reason' => 'Long validity period'];
    }

    /**
     * Score discount attractiveness based on context.
     *
     * @return array{score: float, reason: string}
     */
    private function scoreAttractiveness(Voucher $voucher, int $cartValue, array $features): array
    {
        $voucherValue = $voucher->value ?? 0;
        $type = $voucher->type instanceof \AIArmada\Vouchers\Enums\VoucherType ? $voucher->type->value : 'percentage';
        $bucket = $features['cart_value_bucket'] ?? 'medium';

        // Psychological pricing effects
        if ($type === 'percentage') {
            // Round numbers are more appealing (10%, 20%, 25%)
            if (in_array($voucherValue, [10, 15, 20, 25, 30, 50])) {
                return ['score' => 0.8, 'reason' => 'Psychologically appealing percentage'];
            }

            return ['score' => 0.5, 'reason' => 'Standard percentage discount'];
        }

        // Fixed amounts
        if ($type === 'fixed') {
            // For large carts, fixed amounts look smaller
            if (in_array($bucket, ['premium', 'luxury'])) {
                return ['score' => 0.4, 'reason' => 'Fixed amount may seem small for large cart'];
            }

            // For small carts, fixed amounts are attractive
            if (in_array($bucket, ['micro', 'small'])) {
                return ['score' => 0.8, 'reason' => 'Fixed amount attractive for small cart'];
            }

            return ['score' => 0.6, 'reason' => 'Standard fixed discount'];
        }

        return ['score' => 0.5, 'reason' => 'Unknown voucher type'];
    }

    /**
     * Score usage potential (how much of the voucher capacity remains).
     *
     * @return array{score: float, reason: string}
     */
    private function scoreUsagePotential(Voucher $voucher): array
    {
        $maxUsage = $voucher->usage_limit;
        $currentUsage = $voucher->times_used ?? 0;

        if ($maxUsage === null) {
            return ['score' => 0.3, 'reason' => 'Unlimited usage'];
        }

        $usagePercent = ($currentUsage / $maxUsage) * 100;

        // Scarcity effect
        if ($usagePercent >= 90) {
            return ['score' => 0.8, 'reason' => 'Almost depleted, scarcity effect'];
        }

        if ($usagePercent >= 75) {
            return ['score' => 0.5, 'reason' => 'Popular voucher'];
        }

        if ($usagePercent >= 50) {
            return ['score' => 0.3, 'reason' => 'Moderate usage'];
        }

        return ['score' => 0.1, 'reason' => 'Low usage, may not be popular'];
    }
}
