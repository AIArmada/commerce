<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateRankResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateRankResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateRank extends CreateRecord
{
    protected static string $resource = AffiliateRankResource::class;
}
