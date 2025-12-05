<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Contracts;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\AI\ConversionPrediction;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for conversion prediction services.
 *
 * Implementations can range from simple rule-based heuristics
 * to sophisticated ML models (AWS SageMaker, TensorFlow, etc.)
 */
interface ConversionPredictorInterface
{
    /**
     * Predict the likelihood of cart conversion.
     *
     * @param Cart $cart The cart to analyze
     * @param VoucherCondition|null $voucher Optional voucher being considered
     * @param Model|null $user Optional authenticated user
     */
    public function predictConversion(
        Cart $cart,
        ?VoucherCondition $voucher = null,
        ?Model $user = null,
    ): ConversionPrediction;

    /**
     * Batch predict conversions for multiple carts.
     *
     * @param iterable<Cart> $carts
     * @return iterable<ConversionPrediction>
     */
    public function predictConversionBatch(iterable $carts): iterable;

    /**
     * Get the predictor's name for identification.
     */
    public function getName(): string;

    /**
     * Check if the predictor is ready to make predictions.
     */
    public function isReady(): bool;
}
