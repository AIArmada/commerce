<?php

declare(strict_types=1);

namespace AIArmada\Tax\Data;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Akaunting\Money\Currency;
use Akaunting\Money\Money;

final readonly class TaxResultData
{
    /**
     * @param  array<int, array{name: string, rate: int, amount: int, is_compound: bool}>  $breakdown
     */
    public function __construct(
        public int $taxAmount,
        public ?string $rateId,
        public string $rateName,
        /** Rate in basis points (e.g. 600 = 6.00%) */
        public int $ratePercentage,
        public ?string $zoneId,
        public string $zoneName,
        public bool $includedInPrice = false,
        public ?string $exemptionReason = null,
        public array $breakdown = [],
        public string $currency = 'MYR',
    ) {}

    public function isExempt(): bool
    {
        return $this->exemptionReason !== null;
    }

    public function getFormattedAmount(?string $currency = null): string
    {
        return MoneyFormatter::formatMinor($this->taxAmount, $currency ?? $this->currency);
    }

    public function getFormattedRate(): string
    {
        return number_format($this->ratePercentage / 100, 2) . '%';
    }

    public function getSummary(): string
    {
        if ($this->isExempt()) {
            return $this->exemptionReason ?? 'Tax Exempt';
        }

        return sprintf(
            '%s (%s)',
            $this->rateName,
            $this->getFormattedRate()
        );
    }

    public function hasCompoundTaxes(): bool
    {
        foreach ($this->breakdown as $entry) {
            if ($entry['is_compound']) {
                return true;
            }
        }

        return false;
    }

    public function getMoney(): Money
    {
        $currency = mb_strtoupper($this->currency);
        $currencies = Currency::getCurrencies();

        if (! isset($currencies[$currency])) {
            $currency = mb_strtoupper((string) config('tax.defaults.currency', 'MYR'));
        }

        if (! isset($currencies[$currency])) {
            $currency = 'MYR';
        }

        return Money::{$currency}($this->taxAmount);
    }
}
