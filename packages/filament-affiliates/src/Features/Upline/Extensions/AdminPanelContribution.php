<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Upline\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankHistoryResource;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankResource;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateUplineResource;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Widgets\UplineVisualizationWidget;

final class AdminPanelContribution implements ContributionContract
{
    public function priority(): int
    {
        return 70;
    }

    public function resources(array $features): array
    {
        return array_values(array_filter([
            ($features['ranks'] ?? false) ? AffiliateRankResource::class : null,
            ($features['ranks'] ?? false) ? AffiliateRankHistoryResource::class : null,
            ($features['upline_visualization'] ?? false) ? AffiliateUplineResource::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }

    public function pages(array $features): array
    {
        return [];
    }

    public function widgets(array $features): array
    {
        return array_values(array_filter([
            ($features['upline_visualization'] ?? false) ? UplineVisualizationWidget::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }
}
