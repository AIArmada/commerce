<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateCreativeResource\Pages;

use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateCreativeResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAffiliateCreative extends CreateRecord
{
    protected static string $resource = AffiliateCreativeResource::class;
}
