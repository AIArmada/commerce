<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages;

use AIArmada\Addressing\Actions\SaveAddressAreaAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Support\AddressAreaHierarchy;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LogicException;

final class EditAddressArea extends EditRecord
{
    protected static string $resource = AddressAreaResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressAreaResource::isReadOnly();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['parent_id']) && $this->record instanceof AddressArea) {
            $parent = AddressArea::query()->find($data['parent_id']);

            if ($parent instanceof AddressArea) {
                $message = AddressAreaHierarchy::validateParentAssignment($this->record, $parent);

                if ($message !== null) {
                    throw ValidationException::withMessages([
                        'parent_id' => $message,
                    ]);
                }
            }
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): AddressArea
    {
        if (! $record instanceof AddressArea) {
            throw new LogicException('Expected an address area record.');
        }

        return app(SaveAddressAreaAction::class)->handle($data, $record);
    }
}
