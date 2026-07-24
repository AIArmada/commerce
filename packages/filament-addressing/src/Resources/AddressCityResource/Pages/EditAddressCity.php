<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressCityResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressCityResource;
use Filament\Resources\Pages\EditRecord;

final class EditAddressCity extends EditRecord
{
    protected static string $resource = AddressCityResource::class;
}
