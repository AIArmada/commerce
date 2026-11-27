<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeSetResource\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\FilamentProducts\Resources\AttributeSetResource;
use AIArmada\Products\Actions\SaveAttributeSet;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAttributeSet extends EditRecord
{
    protected static string $resource = AttributeSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): AttributeSet
    {
        abort_unless($record instanceof AttributeSet, 404);

        return app(SaveAttributeSet::class)->update($record, $data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('setAttributes', $data)) {
            /** @var array<int, string>|null $attributes */
            $attributes = is_array($data['setAttributes'] ?? null) ? $data['setAttributes'] : null;
            $data['setAttributes'] = OwnerScopedIds::ensureAllowed('setAttributes', Attribute::class, $attributes);
        }

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
