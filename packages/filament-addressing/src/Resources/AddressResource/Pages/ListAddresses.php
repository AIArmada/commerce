<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressResource;
use Filament\Resources\Pages\ListRecords;

final class ListAddresses extends ListRecords
{
    protected static string $resource = AddressResource::class;
}
