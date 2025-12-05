<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud\Detectors;

use AIArmada\Vouchers\Fraud\Enums\FraudSignalType;
use AIArmada\Vouchers\Fraud\FraudSignal;
use Illuminate\Database\Eloquent\Model;

/**
 * Detects behavioral fraud patterns.
 *
 * Monitors for suspicious user behaviors:
 * - Only using discounted purchases
 * - High refund rates
 * - Cart manipulation patterns
 * - Suspicious checkout patterns
 * - Abnormal cart values
 */
class BehavioralDetector extends AbstractFraudDetector
{
    /**
     * Minimum orders to consider for discount-only analysis.
     */
    protected int $minOrdersForAnalysis = 5;

    /**
     * Threshold for discount-only purchases (percentage).
     */
    protected float $discountOnlyThreshold = 0.9; // 90%

    /**
     * Threshold for high refund rate (percentage).
     */
    protected float $highRefundRateThreshold = 0.3; // 30%

    /**
     * Cart modification count that triggers suspicion.
     */
    protected int $suspiciousCartModificationCount = 10;

    /**
     * Minimum cart value for abnormally high detection.
     */
    protected float $abnormallyHighCartValue = 10000.0;

    /**
     * Maximum cart value for abnormally low detection (with coupon).
     */
    protected float $abnormallyLowCartValue = 1.0;

    public function getName(): string
    {
        return 'behavioral';
    }

    public function getCategory(): string
    {
        return 'behavioral';
    }

    /**
     * Configure thresholds.
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): static
    {
        if (isset($config['min_orders_for_analysis'])) {
            $this->minOrdersForAnalysis = (int) $config['min_orders_for_analysis'];
        }
        if (isset($config['discount_only_threshold'])) {
            $this->discountOnlyThreshold = (float) $config['discount_only_threshold'];
        }
        if (isset($config['high_refund_rate_threshold'])) {
            $this->highRefundRateThreshold = (float) $config['high_refund_rate_threshold'];
        }
        if (isset($config['suspicious_cart_modification_count'])) {
            $this->suspiciousCartModificationCount = (int) $config['suspicious_cart_modification_count'];
        }
        if (isset($config['abnormally_high_cart_value'])) {
            $this->abnormallyHighCartValue = (float) $config['abnormally_high_cart_value'];
        }
        if (isset($config['abnormally_low_cart_value'])) {
            $this->abnormallyLowCartValue = (float) $config['abnormally_low_cart_value'];
        }

        return $this;
    }

    protected function analyze(
        string $code,
        object $cart,
        ?Model $user,
        array $context,
    ): void {
        $this->checkOnlyDiscountedPurchases($user, $context);
        $this->checkHighRefundRate($user, $context);
        $this->checkCartManipulation($cart, $context);
        $this->checkSuspiciousCheckoutPattern($context);
        $this->checkAbnormalCartValue($cart, $context);
    }

    /**
     * Check if user only makes discounted purchases.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkOnlyDiscountedPurchases(?Model $user, array $context): void
    {
        if ($user === null) {
            return;
        }

        $totalOrders = $this->getContextValue($context, 'user_total_orders', 0);
        $discountedOrders = $this->getContextValue($context, 'user_discounted_orders', 0);

        if ($totalOrders < $this->minOrdersForAnalysis) {
            return;
        }

        $discountRate = $discountedOrders / $totalOrders;

        if ($discountRate >= $this->discountOnlyThreshold) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::OnlyDiscountedPurchases,
                score: min(50, 25 + ($discountRate - $this->discountOnlyThreshold) * 250),
                message: sprintf(
                    'User has %.0f%% discounted orders (%d of %d)',
                    $discountRate * 100,
                    $discountedOrders,
                    $totalOrders
                ),
                metadata: [
                    'total_orders' => $totalOrders,
                    'discounted_orders' => $discountedOrders,
                    'discount_rate' => $discountRate,
                    'threshold' => $this->discountOnlyThreshold,
                ],
            ));
        }
    }

    /**
     * Check for high refund rate.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkHighRefundRate(?Model $user, array $context): void
    {
        if ($user === null) {
            return;
        }

        $totalOrders = $this->getContextValue($context, 'user_total_orders', 0);
        $refundedOrders = $this->getContextValue($context, 'user_refunded_orders', 0);

        if ($totalOrders < $this->minOrdersForAnalysis) {
            return;
        }

        $refundRate = $refundedOrders / $totalOrders;

        if ($refundRate >= $this->highRefundRateThreshold) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::HighRefundRate,
                score: min(60, 30 + ($refundRate - $this->highRefundRateThreshold) * 200),
                message: sprintf(
                    'User has %.0f%% refund rate (%d of %d orders)',
                    $refundRate * 100,
                    $refundedOrders,
                    $totalOrders
                ),
                metadata: [
                    'total_orders' => $totalOrders,
                    'refunded_orders' => $refundedOrders,
                    'refund_rate' => $refundRate,
                    'threshold' => $this->highRefundRateThreshold,
                ],
            ));
        }
    }

    /**
     * Check for suspicious cart manipulation.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkCartManipulation(object $cart, array $context): void
    {
        $cartModifications = $this->getContextValue($context, 'cart_modification_count', 0);
        $couponAddRemoves = $this->getContextValue($context, 'coupon_add_remove_count', 0);

        // Check for excessive cart modifications
        if ($cartModifications >= $this->suspiciousCartModificationCount) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::CartManipulation,
                score: min(40, 20 + ($cartModifications - $this->suspiciousCartModificationCount) * 2),
                message: "Excessive cart modifications: {$cartModifications} changes",
                metadata: [
                    'modification_count' => $cartModifications,
                    'threshold' => $this->suspiciousCartModificationCount,
                ],
            ));
        }

        // Check for coupon cycling (add/remove repeatedly)
        if ($couponAddRemoves >= 5) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::CartManipulation,
                score: min(50, 25 + $couponAddRemoves * 5),
                message: "Coupon cycling detected: {$couponAddRemoves} add/remove operations",
                metadata: [
                    'coupon_add_remove_count' => $couponAddRemoves,
                ],
            ));
        }
    }

    /**
     * Check for suspicious checkout patterns.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkSuspiciousCheckoutPattern(array $context): void
    {
        $checkoutAttempts = $this->getContextValue($context, 'checkout_attempt_count', 0);
        $paymentFailures = $this->getContextValue($context, 'payment_failure_count', 0);
        $abandonedCheckouts = $this->getContextValue($context, 'abandoned_checkout_count', 0);

        // Multiple checkout attempts
        if ($checkoutAttempts >= 5) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::SuspiciousCheckoutPattern,
                score: min(35, 15 + $checkoutAttempts * 4),
                message: "Multiple checkout attempts: {$checkoutAttempts} in this session",
                metadata: [
                    'checkout_attempts' => $checkoutAttempts,
                ],
            ));
        }

        // Payment failures with coupon
        if ($paymentFailures >= 3) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::SuspiciousCheckoutPattern,
                score: min(50, 25 + $paymentFailures * 8),
                message: "Multiple payment failures: {$paymentFailures} failures with coupon applied",
                metadata: [
                    'payment_failures' => $paymentFailures,
                ],
            ));
        }

        // Abandoned checkouts with coupons (possible testing)
        if ($abandonedCheckouts >= 3) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::SuspiciousCheckoutPattern,
                score: min(30, 15 + $abandonedCheckouts * 5),
                message: "Pattern of abandoned checkouts with coupons: {$abandonedCheckouts} instances",
                metadata: [
                    'abandoned_checkouts' => $abandonedCheckouts,
                ],
            ));
        }
    }

    /**
     * Check for abnormal cart values.
     *
     * @param  array<string, mixed>  $context
     */
    protected function checkAbnormalCartValue(object $cart, array $context): void
    {
        $cartTotal = $this->getCartTotal($cart);
        $discountAmount = $this->getContextValue($context, 'discount_amount', 0);

        if ($cartTotal === null) {
            return;
        }

        // Abnormally high cart value
        if ($cartTotal >= $this->abnormallyHighCartValue) {
            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::AbnormalCartValue,
                score: min(45, 20 + ($cartTotal / $this->abnormallyHighCartValue) * 15),
                message: sprintf('Unusually high cart value: $%.2f', $cartTotal),
                metadata: [
                    'cart_total' => $cartTotal,
                    'threshold' => $this->abnormallyHighCartValue,
                    'type' => 'high',
                ],
            ));
        }

        // Abnormally low final value after discount (possible manipulation)
        $finalValue = $cartTotal - $discountAmount;
        if ($finalValue <= $this->abnormallyLowCartValue && $discountAmount > 0) {
            $discountPercent = ($discountAmount / $cartTotal) * 100;

            $this->addSignal(FraudSignal::withScore(
                type: FraudSignalType::AbnormalCartValue,
                score: min(70, 35 + $discountPercent / 3),
                message: sprintf(
                    'Near-zero final value after discount: $%.2f (%.1f%% off)',
                    $finalValue,
                    $discountPercent
                ),
                metadata: [
                    'original_total' => $cartTotal,
                    'discount_amount' => $discountAmount,
                    'final_value' => $finalValue,
                    'discount_percent' => $discountPercent,
                    'type' => 'low',
                ],
            ));
        }
    }

    /**
     * Get the cart total from the cart object.
     */
    protected function getCartTotal(object $cart): ?float
    {
        if (method_exists($cart, 'getTotal')) {
            return (float) $cart->getTotal();
        }

        if (property_exists($cart, 'total')) {
            return (float) $cart->total;
        }

        return null;
    }
}
