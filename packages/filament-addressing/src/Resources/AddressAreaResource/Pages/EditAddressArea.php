<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Resources\Pages\EditRecord;

final class EditAddressArea extends EditRecord
{
    protected static string $resource = AddressAreaResource::class;
}
