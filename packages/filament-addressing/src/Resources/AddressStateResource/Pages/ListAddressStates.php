<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressStateResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressStateResource;
use Filament\Resources\Pages\ListRecords;

final class ListAddressStates extends ListRecords
{
    protected static string $resource = AddressStateResource::class;
}
