<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateCreativeResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateCreativeResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateCreative extends CreateRecord
{
    protected static string $resource = AffiliateCreativeResource::class;
}
