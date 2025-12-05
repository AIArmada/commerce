<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\AI\Contracts\CartFeatureExtractorInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Extracts ML-ready features from cart, user, and request context.
 *
 * This class produces a standardized feature vector that can be used
 * by rule-based predictors or exported for ML model training.
 */
final class CartFeatureExtractor implements CartFeatureExtractorInterface
{
    /**
     * Extract all features from the given context.
     *
     * @param Cart $cart
     * @param Model|null $user
     * @param Request|null $request
     * @return array<string, mixed>
     */
    public function extract(Cart $cart, ?Model $user = null, ?Request $request = null): array
    {
        return array_merge(
            $this->extractCartFeatures($cart),
            $this->extractUserFeatures($user),
            $this->extractSessionFeatures($request),
            $this->extractTimeFeatures(),
        );
    }

    /**
     * Extract cart-related features.
     *
     * @return array<string, mixed>
     */
    public function extractCartFeatures(Cart $cart): array
    {
        $items = $cart->getItems();
        $subtotal = $cart->getRawSubtotal();
        $itemCount = $cart->countItems();

        return [
            // Value features
            'cart_value_cents' => $subtotal,
            'cart_value_bucket' => $this->getValueBucket($subtotal),

            // Item features
            'item_count' => $itemCount,
            'unique_items' => $items->count(),
            'avg_item_price_cents' => $itemCount > 0 ? (int) ($subtotal / $itemCount) : 0,
            'max_item_price_cents' => $this->getMaxItemPrice($items),
            'min_item_price_cents' => $this->getMinItemPrice($items),
            'price_variance' => $this->getPriceVariance($items),

            // Cart composition
            'has_single_item' => $itemCount === 1,
            'has_bulk_purchase' => $itemCount >= 5,
            'has_high_value_items' => $this->hasHighValueItems($items),

            // Condition features
            'has_conditions' => $cart->getConditions()->isNotEmpty(),
            'conditions_count' => $cart->getConditions()->count(),
            'total_discount_cents' => $this->getTotalDiscountCents($cart),
            'discount_percentage' => $subtotal > 0 ? ($this->getTotalDiscountCents($cart) / $subtotal) * 100 : 0,

            // Cart metadata
            'cart_age_minutes' => $this->getCartAgeMinutes($cart),
            'modifications_count' => $cart->getMetadata('modifications') ?? 0,
        ];
    }

    /**
     * Extract user-related features.
     *
     * @return array<string, mixed>
     */
    public function extractUserFeatures(?Model $user): array
    {
        if ($user === null) {
            return [
                'is_authenticated' => false,
                'user_order_count' => 0,
                'user_lifetime_value_cents' => 0,
                'user_avg_order_value_cents' => 0,
                'days_since_last_order' => null,
                'user_segment' => 'guest',
                'is_new_customer' => true,
                'voucher_usage_rate' => 0.0,
                'user_refund_rate' => 0.0,
            ];
        }

        return [
            'is_authenticated' => true,
            'user_order_count' => $this->getUserAttribute($user, 'orders_count', 0),
            'user_lifetime_value_cents' => $this->getUserAttribute($user, 'lifetime_value', 0),
            'user_avg_order_value_cents' => $this->getUserAttribute($user, 'average_order_value', 0),
            'days_since_last_order' => $this->getDaysSinceLastOrder($user),
            'user_segment' => $this->getUserAttribute($user, 'segment', 'standard'),
            'is_new_customer' => $this->getUserAttribute($user, 'orders_count', 0) === 0,
            'voucher_usage_rate' => $this->calculateVoucherUsageRate($user),
            'user_refund_rate' => $this->calculateRefundRate($user),
        ];
    }

    /**
     * Extract session/request-related features.
     *
     * @return array<string, mixed>
     */
    public function extractSessionFeatures(?Request $request): array
    {
        if ($request === null) {
            return [
                'session_duration_seconds' => 0,
                'pages_viewed' => 0,
                'device_type' => 'unknown',
                'is_mobile' => false,
                'is_returning_visitor' => false,
                'referrer_type' => 'unknown',
                'channel' => 'web',
            ];
        }

        $userAgent = $request->userAgent() ?? '';

        return [
            'session_duration_seconds' => session()->get('duration', 0),
            'pages_viewed' => session()->get('pages_viewed', 0),
            'device_type' => $this->detectDeviceType($userAgent),
            'is_mobile' => $this->isMobile($userAgent),
            'is_returning_visitor' => session()->has('returning'),
            'referrer_type' => $this->categorizeReferrer($request->header('Referer')),
            'channel' => $request->header('X-Channel', 'web'),
        ];
    }

    /**
     * Extract time-related features.
     *
     * @return array<string, mixed>
     */
    public function extractTimeFeatures(): array
    {
        $now = now();

        return [
            'hour_of_day' => $now->hour,
            'day_of_week' => $now->dayOfWeek,
            'is_weekend' => $now->isWeekend(),
            'is_business_hours' => $now->hour >= 9 && $now->hour < 17,
            'is_evening' => $now->hour >= 18 || $now->hour < 6,
            'month_of_year' => $now->month,
            'is_end_of_month' => $now->day >= 25,
            'day_of_month' => $now->day,
        ];
    }

    /**
     * Get value bucket for cart total.
     */
    private function getValueBucket(int $cents): string
    {
        return match (true) {
            $cents < 2500 => 'micro',       // < $25
            $cents < 5000 => 'small',       // $25-50
            $cents < 10000 => 'medium',     // $50-100
            $cents < 25000 => 'large',      // $100-250
            $cents < 50000 => 'premium',    // $250-500
            default => 'luxury',            // $500+
        };
    }

    /**
     * Get max item price from collection.
     *
     * @param \Illuminate\Support\Collection $items
     */
    private function getMaxItemPrice($items): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        return (int) $items->max(fn ($item) => $item->getRawPrice());
    }

    /**
     * Get min item price from collection.
     *
     * @param \Illuminate\Support\Collection $items
     */
    private function getMinItemPrice($items): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        return (int) $items->min(fn ($item) => $item->getRawPrice());
    }

    /**
     * Calculate price variance coefficient.
     *
     * @param \Illuminate\Support\Collection $items
     */
    private function getPriceVariance($items): float
    {
        if ($items->count() < 2) {
            return 0.0;
        }

        $prices = $items->map(fn ($item) => $item->getRawPrice())->values()->all();
        $mean = array_sum($prices) / count($prices);

        if ($mean === 0.0) {
            return 0.0;
        }

        $variance = array_sum(array_map(fn ($p) => pow($p - $mean, 2), $prices)) / count($prices);

        return sqrt($variance) / $mean;
    }

    /**
     * Check if cart has high-value items (> $100).
     *
     * @param \Illuminate\Support\Collection $items
     */
    private function hasHighValueItems($items): bool
    {
        return $items->contains(fn ($item) => $item->getRawPrice() >= 10000);
    }

    /**
     * Calculate total discount in cents.
     */
    private function getTotalDiscountCents(Cart $cart): int
    {
        $total = 0;
        $cartTotal = $cart->getRawSubtotal();

        foreach ($cart->getConditions() as $condition) {
            // getCalculatedValue returns the adjustment (negative for discounts)
            $adjustment = $condition->getCalculatedValue($cartTotal);
            if ($adjustment < 0) {
                $total += abs($adjustment);
            }
        }

        return (int) $total;
    }

    /**
     * Get cart age in minutes.
     */
    private function getCartAgeMinutes(Cart $cart): int
    {
        $createdAt = $cart->getMetadata('created_at');

        if ($createdAt === null) {
            return 0;
        }

        if (is_string($createdAt)) {
            $createdAt = \Carbon\Carbon::parse($createdAt);
        }

        return (int) $createdAt->diffInMinutes(now());
    }

    /**
     * Get user attribute with fallback.
     */
    private function getUserAttribute(Model $user, string $key, mixed $default): mixed
    {
        return $user->getAttribute($key) ?? $default;
    }

    /**
     * Calculate days since last order.
     */
    private function getDaysSinceLastOrder(Model $user): ?int
    {
        $lastOrderAt = $user->getAttribute('last_order_at');

        if ($lastOrderAt === null) {
            return null;
        }

        if (is_string($lastOrderAt)) {
            $lastOrderAt = \Carbon\Carbon::parse($lastOrderAt);
        }

        return (int) $lastOrderAt->diffInDays(now());
    }

    /**
     * Calculate voucher usage rate.
     */
    private function calculateVoucherUsageRate(Model $user): float
    {
        $orderCount = $this->getUserAttribute($user, 'orders_count', 0);

        if ($orderCount === 0) {
            return 0.0;
        }

        $voucherOrderCount = $this->getUserAttribute($user, 'voucher_orders_count', 0);

        return $voucherOrderCount / $orderCount;
    }

    /**
     * Calculate refund rate.
     */
    private function calculateRefundRate(Model $user): float
    {
        $orderCount = $this->getUserAttribute($user, 'orders_count', 0);

        if ($orderCount === 0) {
            return 0.0;
        }

        $refundCount = $this->getUserAttribute($user, 'refund_count', 0);

        return $refundCount / $orderCount;
    }

    /**
     * Detect device type from user agent.
     */
    private function detectDeviceType(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android')) {
            return 'mobile';
        }

        if (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Check if request is from mobile device.
     */
    private function isMobile(string $userAgent): bool
    {
        return $this->detectDeviceType($userAgent) === 'mobile';
    }

    /**
     * Categorize referrer URL.
     */
    private function categorizeReferrer(?string $referrer): string
    {
        if ($referrer === null || $referrer === '') {
            return 'direct';
        }

        $referrer = strtolower($referrer);

        if (str_contains($referrer, 'google') || str_contains($referrer, 'bing')) {
            return 'search';
        }

        if (str_contains($referrer, 'facebook') || str_contains($referrer, 'twitter') || str_contains($referrer, 'instagram')) {
            return 'social';
        }

        if (str_contains($referrer, 'mail') || str_contains($referrer, 'email')) {
            return 'email';
        }

        return 'referral';
    }
}
