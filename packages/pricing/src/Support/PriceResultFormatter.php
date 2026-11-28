<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Support;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Pricing\Data\PriceResultData;
use AIArmada\Pricing\Exceptions\InvalidCurrencyException;
use Akaunting\Money\Currency;
use Akaunting\Money\Money;

final readonly class PriceResultFormatter
{
    public static function getFormattedSavings(PriceResultData $result): string
    {
        return MoneyFormatter::formatMinor($result->discountAmount, $result->currency);
    }

    public static function getFormattedFinalPrice(PriceResultData $result): string
    {
        return MoneyFormatter::formatMinor($result->finalPrice, $result->currency);
    }

    public static function getFormattedOriginalPrice(PriceResultData $result): string
    {
        return MoneyFormatter::formatMinor($result->originalPrice, $result->currency);
    }

    public static function getMoney(PriceResultData $result): Money
    {
        $currency = self::validatedCurrency($result);

        return Money::{$currency}($result->finalPrice);
    }

    public static function getSavingsMoney(PriceResultData $result): Money
    {
        $currency = self::validatedCurrency($result);

        return Money::{$currency}($result->discountAmount);
    }

    public static function getOriginalMoney(PriceResultData $result): Money
    {
        $currency = self::validatedCurrency($result);

        return Money::{$currency}($result->originalPrice);
    }

    private static function validatedCurrency(PriceResultData $result): string
    {
        $currency = mb_strtoupper(mb_trim($result->currency));

        if ($currency === '' || ! array_key_exists($currency, Currency::getCurrencies())) {
            throw new InvalidCurrencyException($result->currency);
        }

        return $currency;
    }
}
