<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressCityResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressCityResource;
use Filament\Resources\Pages\ListRecords;

final class ListAddressCities extends ListRecords
{
    protected static string $resource = AddressCityResource::class;
}
