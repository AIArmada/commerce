<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateVolumeTierResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateVolumeTierResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateVolumeTier extends CreateRecord
{
    protected static string $resource = AffiliateVolumeTierResource::class;
}
