<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Commissions\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Pages\ManageAffiliateBonusSettings;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Pages\ManageAffiliateCommissionSettings;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Pages\PerformanceBonusesPage;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateVolumeTierResource;

final class AdminPanelContribution implements ContributionContract
{
    public function priority(): int
    {
        return 50;
    }

    public function resources(array $features): array
    {
        return array_values(array_filter([
            ($features['commission_management'] ?? false) ? AffiliateCommissionTemplateResource::class : null,
            ($features['volume_tiers'] ?? false) ? AffiliateVolumeTierResource::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }

    public function pages(array $features): array
    {
        return array_values(array_filter([
            ManageAffiliateCommissionSettings::class,
            ($features['commission_management'] ?? false) ? ManageAffiliateBonusSettings::class : null,
            ($features['payouts'] ?? false) ? PerformanceBonusesPage::class : null,
        ], static fn (?string $class): bool => $class !== null));
    }

    public function widgets(array $features): array
    {
        return [];
    }
}
