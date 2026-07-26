<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\PostalCodeResource\Pages;

use AIArmada\FilamentAddressing\Resources\PostalCodeResource;
use Filament\Resources\Pages\ListRecords;

final class ListPostalCodes extends ListRecords
{
    protected static string $resource = PostalCodeResource::class;
}
