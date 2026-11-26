<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressResource\Pages;

use AIArmada\Addressing\Contracts\AddressWriter;
use AIArmada\Addressing\Features\AddressLifecycle\Models\Address;
use AIArmada\FilamentAddressing\Resources\AddressResource;
use AIArmada\FilamentAddressing\Schemas\AddressFormSchema;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EditAddress extends EditRecord
{
    protected static string $resource = AddressResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressResource::isReadOnly() && parent::canAccess($parameters);
    }

    protected function handleRecordUpdate(Model $record, array $data): Address
    {
        if (! $record instanceof Address) {
            throw new LogicException('Expected an address record.');
        }

        $id = app(AddressWriter::class)->execute(
            $data,
            $this->areaAssignments(),
            (string) $record->getKey(),
            $record::class,
            ['source' => 'filament-addressing'],
        );

        return $record->newQuery()->findOrFail($id);
    }

    /** @return array<string, string|null> */
    private function areaAssignments(): array
    {
        $rawState = $this->form->getRawState();
        $state = is_array($rawState) ? $rawState : $rawState->toArray();

        return AddressFormSchema::extractAreaAssignments($state);
    }
}
