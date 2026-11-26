<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Reporting\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentAffiliates\Features\Reporting\EntryPoints\Filament\Pages\ReportsPage;
use AIArmada\FilamentAffiliates\Features\Reporting\EntryPoints\Filament\Widgets\AffiliateStatsWidget;
use AIArmada\FilamentAffiliates\Features\Reporting\EntryPoints\Filament\Widgets\PerformanceOverviewWidget;
use AIArmada\FilamentAffiliates\Features\Reporting\EntryPoints\Filament\Widgets\RealTimeActivityWidget;

final class AdminPanelContribution implements ContributionContract
{
    public function priority(): int
    {
        return 100;
    }

    public function resources(array $features): array
    {
        return [];
    }

    public function pages(array $features): array
    {
        return array_values(array_filter([
            ($features['reports'] ?? false) ? ReportsPage::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }

    public function widgets(array $features): array
    {
        return array_values(array_filter([
            AffiliateStatsWidget::class,
            PerformanceOverviewWidget::class,
            RealTimeActivityWidget::class,
        ], static fn (?string $class): bool => $class !== null));
    }
}
