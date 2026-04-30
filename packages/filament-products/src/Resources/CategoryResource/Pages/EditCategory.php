<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CategoryResource\Pages;

use AIArmada\FilamentProducts\Resources\CategoryResource;
use AIArmada\FilamentProducts\Support\OwnerScope;
use AIArmada\Products\Models\Category;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

final class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['parent_id']) && is_string($data['parent_id'])) {
            $allowed = OwnerScope::allowedIds(Category::class, [$data['parent_id']]);

            if ($allowed === []) {
                unset($data['parent_id']);
            } else {
                $data['parent_id'] = $allowed[0];
            }
        }

        return $data;
    }
}
