<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Widgets;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
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
        $draft = DocStatus::normalize(Draft::class);
        $pending = DocStatus::normalize(Pending::class);
        $sent = DocStatus::normalize(Sent::class);
        $paid = DocStatus::normalize(Paid::class);
        $overdue = DocStatus::normalize(Overdue::class);

        $row = OwnerUiScope::apply(Doc::query(), includeGlobal: false)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft_count', [$draft])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as pending_count', [$pending, $sent])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as paid_count', [$paid])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as overdue_count', [$overdue])
            ->selectRaw('SUM(CASE WHEN status = ? THEN total_minor ELSE 0 END) as paid_revenue', [$paid])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?, ?) THEN total_minor ELSE 0 END) as outstanding_revenue', [$pending, $sent, $overdue])
            ->first();

        $totalDocs = (int) ($row?->getAttribute('total_count') ?? 0);
        $draftCount = (int) ($row?->getAttribute('draft_count') ?? 0);
        $pendingCount = (int) ($row?->getAttribute('pending_count') ?? 0);
        $paidCount = (int) ($row?->getAttribute('paid_count') ?? 0);
        $overdueCount = (int) ($row?->getAttribute('overdue_count') ?? 0);

        $totalRevenue = (int) ($row?->getAttribute('paid_revenue') ?? 0);
        $pendingRevenue = (int) ($row?->getAttribute('outstanding_revenue') ?? 0);

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

    private function formatCurrency(int | string $amountMinor): string
    {
        return MoneyFormatter::formatMinor((int) $amountMinor, (string) config('docs.defaults.currency', 'MYR'));
    }
}
