<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Contracts;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\AI\DiscountRecommendation;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for discount optimization services.
 *
 * Implementations can range from simple rule-based heuristics
 * to sophisticated ML models (AWS SageMaker, TensorFlow, etc.)
 */
interface DiscountOptimizerInterface
{
    /**
     * Find the optimal discount for a cart.
     *
     * @param Cart $cart The cart to optimize discount for
     * @param Model|null $user Optional authenticated user
     * @param array<string, mixed> $constraints Optional constraints (max_discount, min_margin, etc.)
     */
    public function findOptimalDiscount(
        Cart $cart,
        ?Model $user = null,
        array $constraints = [],
    ): DiscountRecommendation;

    /**
     * Evaluate a specific discount amount.
     *
     * @param Cart $cart
     * @param int $discountCents
     * @param Model|null $user
     * @return array{conversion_lift: float, roi: float, recommended: bool}
     */
    public function evaluateDiscount(
        Cart $cart,
        int $discountCents,
        ?Model $user = null,
    ): array;

    /**
     * Get a range of discount recommendations.
     *
     * @param Cart $cart
     * @param Model|null $user
     * @param int $count Number of alternatives to return
     * @return iterable<DiscountRecommendation>
     */
    public function getDiscountAlternatives(
        Cart $cart,
        ?Model $user = null,
        int $count = 5,
    ): iterable;

    /**
     * Get the optimizer's name for identification.
     */
    public function getName(): string;

    /**
     * Check if the optimizer is ready to make recommendations.
     */
    public function isReady(): bool;
}
