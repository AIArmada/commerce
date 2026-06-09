<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Widgets;

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Sent;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class DocStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $docs = Doc::query();

        $totalDocs = (clone $docs)->count();
        $draftCount = (clone $docs)->where('status', DocStatus::normalize(Draft::class))->count();
        $pendingCount = (clone $docs)->whereIn('status', [DocStatus::normalize(Pending::class), DocStatus::normalize(Sent::class)])->count();
        $paidCount = (clone $docs)->where('status', DocStatus::normalize(Paid::class))->count();
        $overdueCount = (clone $docs)->where('status', DocStatus::normalize(Overdue::class))->count();

        $totalRevenue = (clone $docs)->where('status', DocStatus::normalize(Paid::class))->sum('total');

        $pendingRevenue = (clone $docs)
            ->whereIn('status', [DocStatus::normalize(Pending::class), DocStatus::normalize(Sent::class), DocStatus::normalize(Overdue::class)])
            ->sum('total');

        return [
            Stat::make('Total Documents', $totalDocs)
                ->description('All documents')
                ->descriptionIcon(Heroicon::DocumentText)
                ->color('primary'),

            Stat::make('Draft', $draftCount)
                ->description('Awaiting finalization')
                ->descriptionIcon(Heroicon::PencilSquare)
                ->color('gray'),

            Stat::make('Pending/Sent', $pendingCount)
                ->description('Awaiting payment')
                ->descriptionIcon(Heroicon::Clock)
                ->color('warning'),

            Stat::make('Paid', $paidCount)
                ->description($this->formatCurrency($totalRevenue))
                ->descriptionIcon(Heroicon::CheckCircle)
                ->color('success'),

            Stat::make('Overdue', $overdueCount)
                ->description($this->formatCurrency($pendingRevenue) . ' outstanding')
                ->descriptionIcon(Heroicon::ExclamationTriangle)
                ->color($overdueCount > 0 ? 'danger' : 'success'),
        ];
    }

    protected function getColumns(): int
    {
        return 5;
    }

    private function formatCurrency(string | float $amount): string
    {
        $currency = config('docs.defaults.currency', 'MYR');

        return $currency . ' ' . number_format((float) $amount, 2, '.', ',');
    }
}
