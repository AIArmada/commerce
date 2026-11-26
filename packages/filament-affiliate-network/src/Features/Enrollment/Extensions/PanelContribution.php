<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Enrollment\Extensions;

use AIArmada\FilamentAffiliateNetwork\Contracts\PanelContribution as PanelContributionContract;
use AIArmada\FilamentAffiliateNetwork\Features\Enrollment\EntryPoints\Filament\Resources\AffiliateOfferApplicationResource;

final class PanelContribution implements PanelContributionContract
{
    public function priority(): int
    {
        return 30;
    }

    public function resources(): array
    {
        return [AffiliateOfferApplicationResource::class];
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
