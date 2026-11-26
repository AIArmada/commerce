<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Upline\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AffiliateRelationContribution;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Relations\DownlinesRelationManager;

final class AffiliateRelations implements AffiliateRelationContribution
{
    public function relations(): array
    {
        return [
            10 => DownlinesRelationManager::class,
        ];
    }
}
