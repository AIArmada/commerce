<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\AI;

use AIArmada\Vouchers\AI\Enums\PredictionConfidence;

/**
 * Value object representing a conversion prediction.
 *
 * @property-read float $probability Conversion probability (0.0 to 1.0)
 * @property-read float $confidence Model confidence in the prediction
 * @property-read array<string, mixed> $factors Contributing factors
 * @property-read float|null $withVoucher Probability with voucher
 * @property-read float|null $withoutVoucher Probability without voucher
 * @property-read float $incrementalLift Voucher's incremental value
 */
final readonly class ConversionPrediction
{
    /**
     * @param array<string, mixed> $factors
     */
    public function __construct(
        public float $probability,
        public float $confidence,
        public array $factors = [],
        public ?float $withVoucher = null,
        public ?float $withoutVoucher = null,
        public float $incrementalLift = 0.0,
    ) {}

    /**
     * Create a prediction indicating high conversion likelihood.
     */
    public static function high(float $probability = 0.8, float $confidence = 0.7): self
    {
        return new self(
            probability: $probability,
            confidence: $confidence,
            factors: ['prediction_type' => 'high_conversion'],
        );
    }

    /**
     * Create a prediction indicating low conversion likelihood.
     */
    public static function low(float $probability = 0.2, float $confidence = 0.7): self
    {
        return new self(
            probability: $probability,
            confidence: $confidence,
            factors: ['prediction_type' => 'low_conversion'],
        );
    }

    /**
     * Create an uncertain prediction.
     */
    public static function uncertain(): self
    {
        return new self(
            probability: 0.5,
            confidence: 0.3,
            factors: ['prediction_type' => 'uncertain'],
        );
    }

    /**
     * Check if this is a high probability conversion.
     */
    public function isHighProbability(): bool
    {
        return $this->probability >= 0.7;
    }

    /**
     * Check if this is a low probability conversion.
     */
    public function isLowProbability(): bool
    {
        return $this->probability < 0.3;
    }

    /**
     * Check if the voucher provides significant lift.
     */
    public function voucherWorthIt(float $threshold = 0.15): bool
    {
        return $this->incrementalLift >= $threshold;
    }

    /**
     * Check if the voucher might be cannibalizing a sale.
     */
    public function isPotentialCannibalization(): bool
    {
        return $this->withoutVoucher !== null
            && $this->withoutVoucher >= 0.7
            && $this->incrementalLift < 0.1;
    }

    /**
     * Get the confidence level as an enum.
     */
    public function getConfidenceLevel(): PredictionConfidence
    {
        return PredictionConfidence::fromScore($this->confidence);
    }

    /**
     * Check if prediction is trustworthy enough to act on.
     */
    public function isTrustworthy(): bool
    {
        return $this->getConfidenceLevel()->isTrustworthy();
    }

    /**
     * Get a summary of the prediction.
     */
    public function getSummary(): string
    {
        $pct = round($this->probability * 100);
        $conf = round($this->confidence * 100);

        return "Conversion: {$pct}% (confidence: {$conf}%)";
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'probability' => $this->probability,
            'confidence' => $this->confidence,
            'factors' => $this->factors,
            'with_voucher' => $this->withVoucher,
            'without_voucher' => $this->withoutVoucher,
            'incremental_lift' => $this->incrementalLift,
            'is_high_probability' => $this->isHighProbability(),
            'voucher_worth_it' => $this->voucherWorthIt(),
        ];
    }
}
