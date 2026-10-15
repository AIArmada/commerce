<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressStateResource\Pages;

use AIArmada\Addressing\Models\State;
use AIArmada\FilamentAddressing\Resources\AddressStateResource;
use Filament\Resources\Pages\EditRecord;

final class EditAddressState extends EditRecord
{
    protected static string $resource = AddressStateResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressStateResource::isReadOnly();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->getRecord() instanceof State && $this->getRecord()->code === null) {
            unset($data['name']);
        }

        unset($data['code'], $data['country_id']);

        return $data;
    }
}
