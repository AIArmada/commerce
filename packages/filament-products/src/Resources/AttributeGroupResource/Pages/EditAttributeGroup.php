<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeGroupResource\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\FilamentProducts\Resources\AttributeGroupResource;
use AIArmada\Products\Models\Attribute;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditAttributeGroup extends EditRecord
{
    protected static string $resource = AttributeGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
