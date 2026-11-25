<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Postbacks\Extensions;

use AIArmada\FilamentAffiliateNetwork\Contracts\PanelContribution as PanelContributionContract;
use AIArmada\FilamentAffiliateNetwork\Features\Postbacks\EntryPoints\Filament\Resources\CreatorPostbackResource;

final class PanelContribution implements PanelContributionContract
{
    public function priority(): int
    {
        return 50;
    }

    public function resources(): array
    {
        return [CreatorPostbackResource::class];
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
