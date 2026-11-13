<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\FilamentProducts\Support\CompositeComponentsFormData;
use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Actions\CreateProduct as CreateProductAction;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use Filament\Resources\Pages\CreateRecord;

final class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('categories', $data)) {
            /** @var array<int, string>|null $categories */
            $categories = is_array($data['categories'] ?? null) ? $data['categories'] : null;
            $data['categories'] = OwnerScopedIds::ensureAllowed('categories', Category::class, $categories);
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Product
    {
        /** @var array<array-key, mixed> $components */
        $components = is_array($data['components'] ?? null) ? $data['components'] : [];
        unset($data['components']);

        $type = $data['type'] ?? null;
        $type = $type instanceof ProductType ? $type : ProductType::tryFrom((string) $type);

        if ($type === ProductType::Composite) {
            return app(CreateCompositeProduct::class)->handle(
                $data,
                CompositeComponentsFormData::toActionPayload($components),
            );
        }

        return app(CreateProductAction::class)->execute($data);
    }
}
