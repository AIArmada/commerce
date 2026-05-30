<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateCommissionTemplateResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateCommissionTemplate extends CreateRecord
{
    protected static string $resource = AffiliateCommissionTemplateResource::class;
}
