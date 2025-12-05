<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Fraud\Contracts;

use AIArmada\Vouchers\Fraud\FraudDetectorResult;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for individual fraud detectors.
 *
 * Each detector focuses on a specific category of fraud signals
 * (velocity, pattern, behavioral, code abuse).
 */
interface FraudDetectorInterface
{
    /**
     * Analyze a voucher redemption for fraud signals.
     *
     * @param  string  $code  The voucher code being redeemed
     * @param  object  $cart  The cart associated with the redemption
     * @param  Model|null  $user  The user attempting the redemption
     * @param  array<string, mixed>  $context  Additional context (IP, device, etc.)
     * @return FraudDetectorResult  The detection result with any signals found
     */
    public function detect(
        string $code,
        object $cart,
        ?Model $user = null,
        array $context = [],
    ): FraudDetectorResult;

    /**
     * Get the detector's name.
     */
    public function getName(): string;

    /**
     * Get the detector's category.
     */
    public function getCategory(): string;

    /**
     * Check if the detector is enabled.
     */
    public function isEnabled(): bool;
}
