<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Actions;

use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;

final readonly class SaveExchangeRates
{
    /**
     * @param  array<string, float>  $rates
     */
    public function execute(ExchangeRateSettings $settings, string $base, array $rates): void
    {
        $settings->base = $base;
        $settings->rates = $rates;
        $settings->save();
    }
}
