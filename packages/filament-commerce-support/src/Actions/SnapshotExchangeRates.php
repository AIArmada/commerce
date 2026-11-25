<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Actions;

use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use Carbon\CarbonImmutable;

final readonly class SnapshotExchangeRates
{
    /**
     * Record today's rates as a dated snapshot so historical reports never
     * shift with future rate edits.
     *
     * @return array<string, array<string, float>>
     */
    public function execute(ExchangeRateSettings $settings): array
    {
        $history = $settings->history;
        $history[CarbonImmutable::now()->format('Y-m-d')] = $settings->rates;
        ksort($history);

        $settings->history = $history;
        $settings->save();

        return $history;
    }
}
