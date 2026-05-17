<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeResource\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\FilamentProducts\Resources\AttributeResource;
use AIArmada\Products\Models\AttributeGroup;
use Filament\Resources\Pages\CreateRecord;

final class CreateAttribute extends CreateRecord
{
    protected static string $resource = AttributeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('groups', $data)) {
            /** @var array<int, string>|null $groups */
            $groups = is_array($data['groups'] ?? null) ? $data['groups'] : null;
            $data['groups'] = OwnerScopedIds::ensureAllowed('groups', AttributeGroup::class, $groups);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
