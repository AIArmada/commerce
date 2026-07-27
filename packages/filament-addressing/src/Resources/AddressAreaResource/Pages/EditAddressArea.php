<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages;

use AIArmada\Addressing\Actions\SaveAddressAreaAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Support\AddressAreaHierarchy;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Actions;
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->modalDescription(function (AddressArea $record): string {
                    $areaClass = AddressAreaResource::getModel();
                    $childCount = $areaClass::query()->where('parent_id', $record->getKey())->count();

                    if ($childCount > 0) {
                        return "This area has {$childCount} child area(s). Deleting will orphan them (their parent will be set to null). Are you sure?";
                    }

                    return 'Are you sure you\'d like to delete this area?';
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['parent_id']) && $this->record instanceof AddressArea) {
            $areaClass = AddressAreaResource::getModel();
            $parent = $areaClass::query()->find($data['parent_id']);

            if ($parent instanceof AddressArea) {
                $message = AddressAreaHierarchy::validateParentAssignment($this->record, $parent);

                if ($message !== null) {
                    throw ValidationException::withMessages([
                        'parent_id' => $message,
                    ]);
                }

                $childLevel = array_key_exists('level', $data) && $data['level'] !== null && $data['level'] !== ''
                    ? (int) $data['level']
                    : $this->record->level;

                $levelMessage = AddressAreaHierarchy::validateParentCompatibility($parent, $childLevel);

                if ($levelMessage !== null) {
                    throw ValidationException::withMessages([
                        'parent_id' => $levelMessage,
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
