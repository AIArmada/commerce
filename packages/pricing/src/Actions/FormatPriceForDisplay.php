<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\CommerceSupport\Support\MoneyFormatter;

final class FormatPriceForDisplay
{
    public function format(int $amount, string $currency): string
    {
        return MoneyFormatter::formatMinor($amount, $currency);
    }
}
