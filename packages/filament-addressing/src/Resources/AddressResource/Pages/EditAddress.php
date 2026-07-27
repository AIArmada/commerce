<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressResource\Pages;

use AIArmada\Addressing\Actions\SyncAddressAreaAssignmentsAction;
use AIArmada\Addressing\Models\Address;
use AIArmada\FilamentAddressing\Resources\AddressResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($record, $data): void {
            $record->update($data);
            app(SyncAddressAreaAssignmentsAction::class)->execute(
                $record,
                $this->areaAssignments(),
                $record->state_id,
                ['source' => 'filament-addressing'],
            );
        });

        return $record->fresh() ?? $record;
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
