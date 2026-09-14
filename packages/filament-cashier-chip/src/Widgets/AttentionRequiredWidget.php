<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Widgets;

use AIArmada\CashierChip\Enums\SubscriptionStatus;
use AIArmada\FilamentCashierChip\Concerns\InteractsWithCashierChipData;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class AttentionRequiredWidget extends BaseWidget
{
    use InteractsWithCashierChipData;

    protected static ?int $sort = 7;

    #[Override]
    public static function canView(): bool
    {
        return static::hasWidgetOwnerContext();
    }

    protected function getStats(): array
    {
        $counts = $this->getAttentionCounts();

        $totalAttention = $counts['trials'] + $counts['past_due'] + $counts['grace'] + $counts['incomplete'] + $counts['unpaid'];

        return [
            Stat::make('Attention Required', $totalAttention)
                ->description($this->buildDescription($counts['trials'], $counts['past_due'], $counts['grace'], $counts['incomplete'], $counts['unpaid']))
                ->descriptionIcon($totalAttention > 0 ? Heroicon::ExclamationTriangle : Heroicon::CheckCircle)
                ->color($this->getColor($totalAttention)),
        ];
    }

    /**
     * @return array{trials: int, past_due: int, grace: int, incomplete: int, unpaid: int}
     */
    private function getAttentionCounts(): array
    {
        return $this->rememberWidgetValue('attention.counts', function (): array {
            $now = CarbonImmutable::now();
            $inThreeDays = $now->copy()->addDays(3);

            $row = $this->subscriptionQuery()
                ->selectRaw('COUNT(CASE WHEN trial_ends_at >= ? AND trial_ends_at <= ? AND chip_status = ? THEN 1 END) AS trials_ending', [$now, $inThreeDays, SubscriptionStatus::Trialing->value])
                ->selectRaw('COUNT(CASE WHEN chip_status = ? THEN 1 END) AS past_due', [SubscriptionStatus::PastDue->value])
                ->selectRaw('COUNT(CASE WHEN ends_at >= ? AND ends_at <= ? THEN 1 END) AS grace_ending', [$now, $inThreeDays])
                ->selectRaw('COUNT(CASE WHEN chip_status = ? THEN 1 END) AS incomplete', [SubscriptionStatus::Incomplete->value])
                ->selectRaw('COUNT(CASE WHEN chip_status = ? THEN 1 END) AS unpaid', [SubscriptionStatus::Unpaid->value])
                ->first();

            return [
                'trials' => (int) ($row?->getAttribute('trials_ending') ?? 0),
                'past_due' => (int) ($row?->getAttribute('past_due') ?? 0),
                'grace' => (int) ($row?->getAttribute('grace_ending') ?? 0),
                'incomplete' => (int) ($row?->getAttribute('incomplete') ?? 0),
                'unpaid' => (int) ($row?->getAttribute('unpaid') ?? 0),
            ];
        });
    }

    protected function getColumns(): int
    {
        return 1;
    }

    private function buildDescription(int $trials, int $pastDue, int $grace, int $incomplete, int $unpaid): string
    {
        $parts = [];

        if ($trials > 0) {
            $parts[] = "{$trials} trials ending";
        }

        if ($pastDue > 0) {
            $parts[] = "{$pastDue} past due";
        }

        if ($grace > 0) {
            $parts[] = "{$grace} grace ending";
        }

        if ($incomplete > 0) {
            $parts[] = "{$incomplete} incomplete";
        }

        if ($unpaid > 0) {
            $parts[] = "{$unpaid} unpaid";
        }

        if (empty($parts)) {
            return 'All subscriptions healthy';
        }

        return implode(', ', array_slice($parts, 0, 3));
    }

    private function getColor(int $total): string
    {
        if ($total === 0) {
            return 'success';
        }

        if ($total <= 5) {
            return 'warning';
        }

        return 'danger';
    }
}
