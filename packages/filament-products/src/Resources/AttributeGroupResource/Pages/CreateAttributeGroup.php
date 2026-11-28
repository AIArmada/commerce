<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeGroupResource\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource;
use AIArmada\Products\Actions\SaveAttributeGroup;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use Filament\Resources\Pages\CreateRecord;

final class CreateAttributeGroup extends CreateRecord
{
    protected static string $resource = AttributeGroupResource::class;

    protected function handleRecordCreation(array $data): AttributeGroup
    {
        return app(SaveAttributeGroup::class)->create($data);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('attributes', $data)) {
            /** @var array<int, string>|null $attributes */
            $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : null;
            $data['attributes'] = OwnerScopedIds::ensureAllowed('attributes', Attribute::class, $attributes);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
