<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\FilamentProducts\Support\CompositeComponentsFormData;
use AIArmada\Products\Actions\UpdateCompositeProduct;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('categories', $data)) {
            /** @var array<int, string>|null $categories */
            $categories = is_array($data['categories'] ?? null) ? $data['categories'] : null;
            $data['categories'] = OwnerScopedIds::ensureAllowed('categories', Category::class, $categories);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Product, 404);

        /** @var array<array-key, mixed>|null $components */
        $components = is_array($data['components'] ?? null) ? $data['components'] : null;
        unset($data['components']);

        return app(UpdateCompositeProduct::class)->execute(
            $record,
            $data,
            $components === null ? null : CompositeComponentsFormData::toActionPayload($components),
        );
    }
}
