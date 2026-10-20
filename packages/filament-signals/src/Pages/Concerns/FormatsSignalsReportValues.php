<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Pages\Concerns;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentSignals\Support\SignalsUiConfig;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Filament\Support\Facades\FilamentTimezone;

trait FormatsSignalsReportValues
{
    public function formatMoney(int $minor, ?string $currency = null): string
    {
        $code = $currency !== null && mb_trim($currency) !== ''
            ? mb_strtoupper($currency)
            : mb_strtoupper((string) config('signals.defaults.currency', 'MYR'));

        return $code . ' ' . MoneyFormatter::decimalFromMinor($minor, $code);
    }

    /**
     * @param  int|array<string, int>|null  $revenue
     */
    public function formatRevenue(int | array | null $revenue, ?string $currency = null): string
    {
        if (is_array($revenue)) {
            $formatted = [];

            foreach ($revenue as $code => $minor) {
                $formatted[] = $this->formatMoney((int) $minor, (string) $code);
            }

            return $formatted === [] ? $this->formatMoney(0, $currency) : implode(' + ', $formatted);
        }

        return $this->formatMoney((int) ($revenue ?? 0), $currency);
    }

    protected function formatAggregateTimestamp(mixed $state): ?string
    {
        if ($state instanceof DateTimeInterface) {
            return CarbonImmutable::instance($state)
                ->timezone(FilamentTimezone::get())
                ->format('M j, Y g:i A');
        }

        if (! is_string($state) || $state === '') {
            return null;
        }

        return CarbonImmutable::parse($state, 'UTC')
            ->timezone(FilamentTimezone::get())
            ->format('M j, Y g:i A');
    }

    public function outcomesLabel(): string
    {
        return SignalsUiConfig::outcomesLabel();
    }

    public function monetaryValueLabel(): string
    {
        return SignalsUiConfig::monetaryValueLabel();
    }

    public function averageOutcomeRateLabel(): string
    {
        return SignalsUiConfig::averageOutcomeRateLabel();
    }
}
