<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressResource\Pages;

use AIArmada\FilamentAddressing\Resources\AddressResource;
use Filament\Resources\Pages\EditRecord;

final class EditAddress extends EditRecord
{
    protected static string $resource = AddressResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressResource::isReadOnly();
    }
}
