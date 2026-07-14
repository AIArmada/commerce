<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages;

use AIArmada\Addressing\Actions\SaveAddressAreaAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EditAddressArea extends EditRecord
{
    protected static string $resource = AddressAreaResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressAreaResource::isReadOnly();
    }

    protected function handleRecordUpdate(Model $record, array $data): AddressArea
    {
        if (! $record instanceof AddressArea) {
            throw new LogicException('Expected an address area record.');
        }

        return app(SaveAddressAreaAction::class)->handle($data, $record);
    }
}
