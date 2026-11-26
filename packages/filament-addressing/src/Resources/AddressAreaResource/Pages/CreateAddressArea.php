<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages;

use AIArmada\Addressing\Contracts\AddressAreaWriter;
use AIArmada\Addressing\Features\Geography\Models\AddressArea;
use AIArmada\FilamentAddressing\Persistence\AddressingFilterOptions;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Resources\Pages\CreateRecord;
use LogicException;

final class CreateAddressArea extends CreateRecord
{
    protected static string $resource = AddressAreaResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressAreaResource::isReadOnly() && parent::canAccess($parameters);
    }

    protected function handleRecordCreation(array $data): AddressArea
    {
        unset($data['source'], $data['source_id'], $data['parent_source_id']);

        $modelClass = AddressAreaResource::getModel();

        if (! is_a($modelClass, AddressArea::class, true)) {
            throw new LogicException('Invalid addressing resource model.');
        }
        $id = app(AddressAreaWriter::class)->handle($data, modelClass: $modelClass);

        AddressingFilterOptions::forgetAreaOptions();

        return $modelClass::query()->with(['country', 'parent'])->findOrFail($id);
    }
}
