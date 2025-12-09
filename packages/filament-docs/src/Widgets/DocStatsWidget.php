<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Widgets;

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class DocStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalDocs = Doc::count();
        $draftCount = Doc::where('status', DocStatus::DRAFT)->count();
        $pendingCount = Doc::whereIn('status', [DocStatus::PENDING, DocStatus::SENT])->count();
        $paidCount = Doc::where('status', DocStatus::PAID)->count();
        $overdueCount = Doc::where('status', DocStatus::OVERDUE)->count();

        $totalRevenue = Doc::where('status', DocStatus::PAID)
            ->sum('total');

        $pendingRevenue = Doc::whereIn('status', [DocStatus::PENDING, DocStatus::SENT, DocStatus::OVERDUE])
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

        return $currency . ' ' . number_format((float) $amount, 2);
    }
}
