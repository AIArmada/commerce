<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankResource\Pages;

use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Resources\AffiliateRankResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateRank extends CreateRecord
{
    protected static string $resource = AffiliateRankResource::class;
}
