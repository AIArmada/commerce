<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AffiliateRelationContribution;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Relations\ProgramsRelationManager;

final class AffiliateRelations implements AffiliateRelationContribution
{
    public function relations(): array
    {
        return [
            30 => ProgramsRelationManager::class,
        ];
    }
}
