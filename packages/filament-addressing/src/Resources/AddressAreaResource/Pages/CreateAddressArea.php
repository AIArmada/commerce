<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAddressArea extends CreateRecord
{
    protected static string $resource = AddressAreaResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressAreaResource::isReadOnly();
    }
}
