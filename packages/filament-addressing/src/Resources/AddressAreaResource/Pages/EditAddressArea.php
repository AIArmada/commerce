<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages;

use AIArmada\Addressing\Actions\SaveAddressAreaAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Support\AddressAreaHierarchy;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use AIArmada\FilamentAddressing\Support\AddressingFilterOptions;
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
                ->visible(fn (AddressArea $record): bool => $record->source === 'manual')
                ->authorize(fn (AddressArea $record): bool => $record->source === 'manual'
                    && AddressAreaResource::canDelete($record))
                ->modalDescription(function (AddressArea $record): string {
                    $areaClass = AddressAreaResource::getModel();
                    $childCount = $areaClass::query()->where('parent_id', $record->getKey())->count();

                    if ($childCount > 0) {
                        return "This area has {$childCount} child area(s). Deleting will orphan them (their parent will be set to null). Are you sure?";
                    }

                    return 'Are you sure you\'d like to delete this area?';
                })
                ->after(function (): void {
                    AddressingFilterOptions::forgetAreaOptions();
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record instanceof AddressArea && $this->record->source !== 'manual') {
            unset($data['hierarchy_type']);
        } elseif ($this->record instanceof AddressArea && array_key_exists('hierarchy_type', $data)) {
            $currentHierarchyType = $this->record->ancestors()
                ->wherePivot('relationship_type', 'contains')
                ->wherePivot('source', $this->record->source)
                ->first()?->pivot?->getAttribute('hierarchy_type');

            if ($currentHierarchyType === $data['hierarchy_type']) {
                unset($data['hierarchy_type']);
            }
        }

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

        if ($record->source !== 'manual') {
            $data = array_intersect_key($data, ['is_active' => true]);
        } else {
            unset($data['source'], $data['source_id'], $data['parent_source_id']);
        }

        $area = app(SaveAddressAreaAction::class)->handle($data, $record);

        AddressingFilterOptions::forgetAreaOptions();

        return $area;
    }
}
