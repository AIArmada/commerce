<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressResource\Pages;

use AIArmada\Addressing\Actions\SyncAddressAreaAssignmentsAction;
use AIArmada\Addressing\Models\Address;
use AIArmada\FilamentAddressing\Resources\AddressResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EditAddress extends EditRecord
{
    protected static string $resource = AddressResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressResource::isReadOnly();
    }

    protected function handleRecordUpdate(Model $record, array $data): Address
    {
        if (! $record instanceof Address) {
            throw new LogicException('Expected an address record.');
        }

        $record->update($data);
        app(SyncAddressAreaAssignmentsAction::class)->execute($record, $this->areaAssignments());

        return $record->fresh() ?? $record;
    }

    /** @return array<string, string|null> */
    private function areaAssignments(): array
    {
        $rawState = $this->form->getRawState();
        $state = is_array($rawState) ? $rawState : $rawState->toArray();

        return [
            'postal_locality' => $state['postal_area_id'] ?? null,
            'administrative_district' => $state['administrative_district_id'] ?? null,
            'administrative_subdivision' => $state['administrative_subdivision_id'] ?? null,
            'administrative_lower_area' => $state['administrative_lower_area_id'] ?? null,
        ];
    }
}
