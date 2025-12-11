<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CategoryResource\Pages;

use AIArmada\FilamentProducts\Resources\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Handle parent from URL query parameter
        if (request()->has('parent')) {
            $data['parent_id'] = request()->query('parent');
        }

        return $data;
    }
}
