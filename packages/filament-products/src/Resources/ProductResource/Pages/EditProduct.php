<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\Pages;

use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\FilamentProducts\Support\OwnerScope;
use AIArmada\Products\Models\Category;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

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
            $data['categories'] = OwnerScope::ensureAllowed('categories', Category::class, $categories);
        }

        return $data;
    }
}
