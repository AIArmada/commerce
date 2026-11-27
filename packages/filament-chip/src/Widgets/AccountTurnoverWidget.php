<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Widgets;

use AIArmada\Chip\Services\ChipCollectService;
use AIArmada\CommerceSupport\Persistence\OwnerCache;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Override;
use Throwable;

final class AccountTurnoverWidget extends ChartWidget
{
    protected ?string $heading = 'Account Turnover';

    protected ?string $description = 'Revenue and fees over the last 30 days';

    protected static ?int $sort = 21;

    protected ?string $maxHeight = '300px';

    #[Override]
    public static function canView(): bool
    {
        if (! (bool) config('chip.owner.enabled', false)) {
            return true;
        }

        return OwnerContext::resolve() === null && OwnerContext::isExplicitGlobal();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $data = $this->getTurnoverData();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (MYR)',
                    'data' => $data['revenue'],
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Fees (MYR)',
                    'data' => $data['fees'],
                    'backgroundColor' => 'rgba(239, 68, 68, 0.2)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array{labels: array<string>, revenue: array<float>, fees: array<float>}
     */
    private function getTurnoverData(): array
    {
        $labels = [];
        $revenue = [];
        $fees = [];
        $authorized = ! (bool) config('chip.owner.enabled', false)
            || (OwnerContext::resolve() === null && OwnerContext::isExplicitGlobal());

        for ($i = 29; $i >= 0; $i--) {
            $date = CarbonImmutable::now()->subDays($i);
            $labels[] = $date->format('M d');

            if (! $authorized) {
                $revenue[] = 0.0;
                $fees[] = 0.0;

                continue;
            }

            $bucket = OwnerCache::remember(
                OwnerContext::resolve(),
                'filament-chip.account-turnover.' . $date->format('Y-m-d'),
                $date->isToday()
                    ? max(1, (int) config('filament-chip.widgets.turnover_current_bucket_ttl', 60))
                    : max(1, (int) config('filament-chip.widgets.turnover_cache_ttl', 3600)),
                fn (): array => $this->fetchTurnoverBucket($date),
            );

            $revenue[] = round(((float) ($bucket['turnover'] ?? 0)) / 100, 2);
            $fees[] = round(((float) ($bucket['fee_sell'] ?? 0)) / 100, 2);
        }

        return [
            'labels' => $labels,
            'revenue' => $revenue,
            'fees' => $fees,
        ];
    }

    /**
     * @return array{turnover: int|float, fee_sell: int|float}
     */
    private function fetchTurnoverBucket(CarbonImmutable $date): array
    {
        try {
            $response = app(ChipCollectService::class)->getAccountTurnover([
                'from' => $date->startOfDay()->getTimestamp(),
                'to' => $date->endOfDay()->getTimestamp(),
                'currency' => 'MYR',
            ]);
        } catch (Throwable) {
            return ['turnover' => 0, 'fee_sell' => 0];
        }

        return [
            'turnover' => (float) ($response['incoming']['turnover'] ?? 0),
            'fee_sell' => (float) ($response['incoming']['fee_sell'] ?? 0),
        ];
    }
}
