<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateVolumeTierResource\Pages;

use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Resources\AffiliateVolumeTierResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateVolumeTier extends CreateRecord
{
    protected static string $resource = AffiliateVolumeTierResource::class;
}
