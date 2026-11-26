<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Dashboard\Extensions;

use AIArmada\FilamentAffiliateNetwork\Contracts\PanelContribution as PanelContributionContract;
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\EntryPoints\Filament\Pages\MerchantDashboardPage;
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\EntryPoints\Filament\Widgets\NetworkStatsWidget;
use AIArmada\FilamentAffiliateNetwork\Features\Dashboard\EntryPoints\Filament\Widgets\TopOffersWidget;

final class PanelContribution implements PanelContributionContract
{
    public function priority(): int
    {
        return 60;
    }

    public function resources(): array
    {
        return [];
    }

    public function pages(): array
    {
        return [MerchantDashboardPage::class];
    }

    public function widgets(): array
    {
        return [NetworkStatsWidget::class, TopOffersWidget::class];
    }
}
