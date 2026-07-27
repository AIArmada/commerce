<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressResource\Pages;

use AIArmada\Addressing\Actions\SyncAddressAreaAssignmentsAction;
use AIArmada\Addressing\Models\Address;
use AIArmada\FilamentAddressing\Resources\AddressResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

final class CreateAddress extends CreateRecord
{
    protected static string $resource = AddressResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressResource::isReadOnly();
    }

    protected function handleRecordCreation(array $data): Address
    {
        return DB::transaction(function () use ($data): Address {
            $address = new Address;
            $address->fill($data);
            $address->save();

            app(SyncAddressAreaAssignmentsAction::class)->execute(
                $address,
                $this->areaAssignments(),
                $address->state_id,
                ['source' => 'filament-addressing'],
            );

            return $address;
        });
    }

    /** @return array<string, string|null> */
    private function areaAssignments(): array
    {
        $rawState = $this->form->getRawState();
        $state = is_array($rawState) ? $rawState : $rawState->toArray();
        $assignments = $state['area_assignments'] ?? [];

        return is_array($assignments) ? $assignments : [];
    }
}
