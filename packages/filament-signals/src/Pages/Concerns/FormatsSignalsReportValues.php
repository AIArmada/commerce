<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages\Concerns;

use Carbon\CarbonImmutable;

trait FormatsSignalsReportValues
{
    public function formatMoney(int $minor): string
    {
        return config('signals.defaults.currency', 'MYR') . ' ' . number_format($minor / 100, 2);
    }

    protected function formatAggregateTimestamp(mixed $state): ?string
    {
        if (! is_string($state) || $state === '') {
            return null;
        }

        return CarbonImmutable::parse($state)->format('M j, Y g:i A');
    }
}
