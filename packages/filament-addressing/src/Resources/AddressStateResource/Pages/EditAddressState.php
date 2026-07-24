<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressStateResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressStateResource;
use Filament\Resources\Pages\EditRecord;

final class EditAddressState extends EditRecord
{
    protected static string $resource = AddressStateResource::class;
}
