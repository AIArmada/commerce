<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Ledger\Extensions;

use AIArmada\FilamentAffiliateNetwork\Contracts\PanelContribution as PanelContributionContract;
use AIArmada\FilamentAffiliateNetwork\Features\Ledger\EntryPoints\Filament\Resources\MerchantStatementResource;

final class PanelContribution implements PanelContributionContract
{
    public function priority(): int
    {
        return 40;
    }

    public function resources(): array
    {
        return [MerchantStatementResource::class];
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
