<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateCreativeResource\Pages;

use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Resources\AffiliateCreativeResource;
use Filament\Resources\Pages\EditRecord;

final class EditAffiliateCreative extends EditRecord
{
    protected static string $resource = AffiliateCreativeResource::class;
}
