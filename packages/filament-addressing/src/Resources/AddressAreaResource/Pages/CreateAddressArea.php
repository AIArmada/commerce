<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages;

use AIArmada\Addressing\Actions\SaveAddressAreaAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAddressArea extends CreateRecord
{
    protected static string $resource = AddressAreaResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressAreaResource::isReadOnly();
    }

    protected function handleRecordCreation(array $data): AddressArea
    {
        return app(SaveAddressAreaAction::class)->handle($data);
    }
}
