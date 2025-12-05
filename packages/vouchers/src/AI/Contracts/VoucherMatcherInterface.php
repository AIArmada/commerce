<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI\Contracts;

use AIArmada\Cart\Cart;
use AIArmada\Vouchers\AI\VoucherMatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Contract for voucher matching services.
 *
 * Implementations can range from simple rule-based heuristics
 * to sophisticated ML models (AWS SageMaker, TensorFlow, etc.)
 */
interface VoucherMatcherInterface
{
    /**
     * Find the best voucher for a cart.
     *
     * @param Cart $cart The cart to match a voucher for
     * @param Collection $availableVouchers Collection of Voucher models to choose from
     * @param Model|null $user Optional authenticated user
     */
    public function findBestVoucher(
        Cart $cart,
        Collection $availableVouchers,
        ?Model $user = null,
    ): VoucherMatch;

    /**
     * Rank all available vouchers by suitability.
     *
     * @param Cart $cart
     * @param Collection $availableVouchers
     * @param Model|null $user
     * @return Collection<int, VoucherMatch> Sorted by match score descending
     */
    public function rankVouchers(
        Cart $cart,
        Collection $availableVouchers,
        ?Model $user = null,
    ): Collection;

    /**
     * Score a specific voucher for the cart.
     *
     * @param Cart $cart
     * @param mixed $voucher
     * @param Model|null $user
     * @return VoucherMatch
     */
    public function scoreVoucher(
        Cart $cart,
        mixed $voucher,
        ?Model $user = null,
    ): VoucherMatch;

    /**
     * Get the matcher's name for identification.
     */
    public function getName(): string;

    /**
     * Check if the matcher is ready to make recommendations.
     */
    public function isReady(): bool;
}
