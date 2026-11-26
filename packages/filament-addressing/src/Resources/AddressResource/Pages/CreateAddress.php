<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressResource\Pages;

use AIArmada\Addressing\Contracts\AddressWriter;
use AIArmada\Addressing\Models\Address;
use AIArmada\FilamentAddressing\Resources\AddressResource;
use AIArmada\FilamentAddressing\Schemas\AddressFormSchema;
use Filament\Resources\Pages\CreateRecord;
use LogicException;

final class CreateAddress extends CreateRecord
{
    protected static string $resource = AddressResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressResource::isReadOnly() && parent::canAccess($parameters);
    }

    protected function handleRecordCreation(array $data): Address
    {
        $modelClass = AddressResource::getModel();

        if (! is_a($modelClass, Address::class, true)) {
            throw new LogicException('Invalid addressing resource model.');
        }
        $id = app(AddressWriter::class)->execute(
            $data,
            $this->areaAssignments(),
            modelClass: $modelClass,
        );

        return $modelClass::query()->findOrFail($id);
    }

    /** @return array<string, string|null> */
    private function areaAssignments(): array
    {
        $rawState = $this->form->getRawState();
        $state = is_array($rawState) ? $rawState : $rawState->toArray();

        return AddressFormSchema::extractAreaAssignments($state);
    }
}
