<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressCountryResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressCountryResource;
use Filament\Resources\Pages\EditRecord;

final class EditAddressCountry extends EditRecord
{
    protected static string $resource = AddressCountryResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressCountryResource::isReadOnly();
    }
}
