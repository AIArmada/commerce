<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliate extends CreateRecord
{
    protected static string $resource = AffiliateResource::class;
}
