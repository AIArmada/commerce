<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Conversions\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AffiliateRelationContribution;
use AIArmada\FilamentAffiliates\Features\Conversions\EntryPoints\Filament\Relations\ConversionsRelationManager;

final class AffiliateRelations implements AffiliateRelationContribution
{
    public function relations(): array
    {
        return [
            20 => ConversionsRelationManager::class,
        ];
    }
}
