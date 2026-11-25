<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Sites\Extensions;

use AIArmada\FilamentAffiliateNetwork\Contracts\PanelContribution as PanelContributionContract;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Resources\AffiliateSiteResource;

final class PanelContribution implements PanelContributionContract
{
    public function priority(): int
    {
        return 10;
    }

    public function resources(): array
    {
        return [AffiliateSiteResource::class];
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
