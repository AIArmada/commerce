<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Payouts\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AffiliateRelationContribution;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Relations\PayoutHoldsRelationManager;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Relations\PayoutMethodsRelationManager;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Relations\PayoutsRelationManager;

final class AffiliateRelations implements AffiliateRelationContribution
{
    public function relations(): array
    {
        return [
            40 => PayoutsRelationManager::class,
            50 => PayoutMethodsRelationManager::class,
            60 => PayoutHoldsRelationManager::class,
        ];
    }
}
