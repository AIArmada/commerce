<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CategoryResource\Pages;

use AIArmada\FilamentProducts\Resources\CategoryResource;
use AIArmada\Products\Models\Category;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Handle parent from URL query parameter (validated + owner-scoped)
        $parentId = request()->query('parent');
        if (is_string($parentId) && Str::isUuid($parentId)) {
            $isValidParent = Category::query()
                ->forOwner()
                ->whereKey($parentId)
                ->exists();

            if ($isValidParent) {
                $data['parent_id'] = $parentId;
            }
        }

        return $data;
    }
}
