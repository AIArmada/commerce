<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Data;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Pricing\Exceptions\InvalidCurrencyException;
use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use Spatie\LaravelData\Data;

final class PriceResultData extends Data
{
    public function __construct(
        public int $originalPrice,
        public int $finalPrice,
        public int $discountAmount,
        public ?string $discountSource = null,
        public ?float $discountPercentage = null,
        public ?string $priceListName = null,
        public ?string $tierDescription = null,
        public ?string $promotionName = null,
        public string $currency = 'MYR',
        /** @var array<int, array<string, mixed>> */
        public array $breakdown = [],
    ) {}

    public function hasDiscount(): bool
    {
        return $this->discountAmount > 0;
    }

    public function getFormattedSavings(): string
    {
        return MoneyFormatter::formatMinor($this->discountAmount, $this->currency);
    }

    public function getFormattedFinalPrice(): string
    {
        return MoneyFormatter::formatMinor($this->finalPrice, $this->currency);
    }

    public function getFormattedOriginalPrice(): string
    {
        return MoneyFormatter::formatMinor($this->originalPrice, $this->currency);
    }

    public function getMoney(): Money
    {
        $currency = $this->validatedCurrency();

        return Money::{$currency}($this->finalPrice);
    }

    public function getSavingsMoney(): Money
    {
        $currency = $this->validatedCurrency();

        return Money::{$currency}($this->discountAmount);
    }

    public function getOriginalMoney(): Money
    {
        $currency = $this->validatedCurrency();

        return Money::{$currency}($this->originalPrice);
    }

    private function validatedCurrency(): string
    {
        $currency = mb_strtoupper(mb_trim($this->currency));

        if ($currency === '' || ! array_key_exists($currency, Currency::getCurrencies())) {
            throw new InvalidCurrencyException($this->currency);
        }

        return $currency;
    }
}
