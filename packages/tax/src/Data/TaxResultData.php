<?php

declare(strict_types=1);

namespace AIArmada\Tax\Data;

use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use Spatie\LaravelData\Data;

/**
 * Data Transfer Object representing a tax calculation result.
 */
class TaxResultData extends Data
{
    public function __construct(
        public int $taxAmount,
        public TaxRate $rate,
        public TaxZone $zone,
        public bool $includedInPrice = false,
        public ?string $exemptionReason = null,
    ) {}

    /**
     * Check if the result is tax-exempt.
     */
    public function isExempt(): bool
    {
        return $this->exemptionReason !== null || $this->rate->rate === 0;
    }

    /**
     * Get the formatted tax amount.
     */
    public function getFormattedAmount(): string
    {
        return 'RM ' . number_format($this->taxAmount / 100, 2);
    }

    /**
     * Get a summary of the tax calculation.
     */
    public function getSummary(): string
    {
        if ($this->isExempt()) {
            return $this->exemptionReason ?? 'Tax Exempt';
        }

        return sprintf(
            '%s (%s)',
            $this->rate->name,
            $this->rate->getFormattedRate()
        );
    }
}
