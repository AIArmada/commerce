<?php

declare(strict_types=1);

namespace AIArmada\FilamentAddressing\Resources\AddressAreaResource\Pages;

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Support\AddressAreaHierarchy;
use AIArmada\FilamentAddressing\Resources\AddressAreaResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

final class EditAddressArea extends EditRecord
{
    protected static string $resource = AddressAreaResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return ! AddressAreaResource::isReadOnly();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $parentId = $data['parent_id'] ?? null;

        if ($parentId === null || $parentId === '') {
            $data['parent_id'] = null;

            return $data;
        }

        if (! is_string($parentId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Selected parent area is invalid.',
            ]);
        }

        $parent = AddressArea::query()
            ->find($parentId);

        if (! $parent instanceof AddressArea) {
            throw ValidationException::withMessages([
                'parent_id' => 'Selected parent area could not be found.',
            ]);
        }

        $message = AddressAreaHierarchy::validateParentAssignment(
            $this->getRecord() instanceof AddressArea ? $this->getRecord() : null,
            $parent,
        );

        if ($message !== null) {
            throw ValidationException::withMessages([
                'parent_id' => $message,
            ]);
        }

        $data['parent_id'] = $parent->id;

        return $data;
    }
}
