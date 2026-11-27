<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\AttributeResource\Pages;

use AIArmada\Products\Actions\DeleteCatalogRecord;
use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\FilamentProducts\Resources\AttributeResource;
use AIArmada\Products\Actions\SaveAttribute;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditAttribute extends EditRecord
{
    protected static string $resource = AttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->using(fn (Attribute $record): bool => app(DeleteCatalogRecord::class)->execute($record)),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Attribute
    {
        abort_unless($record instanceof Attribute, 404);

        return app(SaveAttribute::class)->update($record, $data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
