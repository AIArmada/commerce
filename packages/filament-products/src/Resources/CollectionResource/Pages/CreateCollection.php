<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CollectionResource\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\FilamentProducts\Resources\CollectionResource;
use AIArmada\Products\Actions\SaveCollection;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use Filament\Resources\Pages\CreateRecord;

final class CreateCollection extends CreateRecord
{
    protected static string $resource = CollectionResource::class;

    protected function handleRecordCreation(array $data): Collection
    {
        return app(SaveCollection::class)->create($data);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('products', $data)) {
            /** @var array<int, string>|null $products */
            $products = is_array($data['products'] ?? null) ? $data['products'] : null;
            $data['products'] = OwnerScopedIds::ensureAllowed('products', Product::class, $products);
        }

        return $data;
    }
}
