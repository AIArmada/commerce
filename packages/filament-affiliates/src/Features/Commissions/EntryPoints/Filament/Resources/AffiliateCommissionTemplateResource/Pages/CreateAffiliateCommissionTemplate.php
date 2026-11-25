<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource\Pages;

use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateCommissionTemplateResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateCommissionTemplate extends CreateRecord
{
    protected static string $resource = AffiliateCommissionTemplateResource::class;
}
