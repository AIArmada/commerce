<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource\Pages;

use AIArmada\FilamentAffiliates\Resources\AffiliateConversionResource;
use Filament\Resources\Pages\ListRecords;

final class ListAffiliateConversions extends ListRecords
{
    protected static string $resource = AffiliateConversionResource::class;
}
