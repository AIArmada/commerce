<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\Pages;

use AIArmada\FilamentProducts\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure prices are stored in cents
        if (isset($data['price']) && is_numeric($data['price'])) {
            $data['price'] = (int) ($data['price'] * 100);
        }
        if (isset($data['compare_price']) && is_numeric($data['compare_price'])) {
            $data['compare_price'] = (int) ($data['compare_price'] * 100);
        }
        if (isset($data['cost']) && is_numeric($data['cost'])) {
            $data['cost'] = (int) ($data['cost'] * 100);
        }

        return $data;
    }
}
