<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Catalog\Extensions;

use AIArmada\FilamentAffiliateNetwork\Contracts\PanelContribution as PanelContributionContract;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferCategoryResource;

final class PanelContribution implements PanelContributionContract
{
    public function priority(): int
    {
        return 20;
    }

    public function resources(): array
    {
        return [AffiliateOfferResource::class, AffiliateOfferCategoryResource::class];
    }

    public function pages(): array
    {
        return [];
    }

    public function widgets(): array
    {
        return [];
    }
}
