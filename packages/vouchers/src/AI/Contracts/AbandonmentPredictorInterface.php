<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Contracts;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\AI\AbandonmentRisk;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for abandonment prediction services.
 *
 * Implementations can range from simple rule-based heuristics
 * to sophisticated ML models (AWS SageMaker, TensorFlow, etc.)
 */
interface AbandonmentPredictorInterface
{
    /**
     * Predict the likelihood of cart abandonment.
     *
     * @param Cart $cart The cart to analyze
     * @param Model|null $user Optional authenticated user
     */
    public function predictAbandonment(
        Cart $cart,
        ?Model $user = null,
    ): AbandonmentRisk;

    /**
     * Batch predict abandonment for multiple carts.
     *
     * @param iterable<Cart> $carts
     * @return iterable<AbandonmentRisk>
     */
    public function predictAbandonmentBatch(iterable $carts): iterable;

    /**
     * Get carts with high abandonment risk.
     *
     * @param iterable<Cart> $carts
     * @param float $threshold Minimum risk score to include
     * @return iterable<array{cart: Cart, risk: AbandonmentRisk}>
     */
    public function getHighRiskCarts(iterable $carts, float $threshold = 0.6): iterable;

    /**
     * Get the predictor's name for identification.
     */
    public function getName(): string;

    /**
     * Check if the predictor is ready to make predictions.
     */
    public function isReady(): bool;
}
