<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Identity\Extensions;

use AIArmada\FilamentAffiliates\Contracts\AffiliateRelationContribution;
use AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Resources\AffiliateResource\RelationManagers\VouchersRelationManager;

final class AffiliateRelations implements AffiliateRelationContribution
{
    public function relations(): array
    {
        return [
            70 => VouchersRelationManager::class,
        ];
    }
}
