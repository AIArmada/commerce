<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\CollectionResource\Pages;

use AIArmada\Products\Actions\DeleteCatalogRecord;
use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\FilamentProducts\Resources\CollectionResource;
use AIArmada\Products\Actions\SaveCollection;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditCollection extends EditRecord
{
    protected static string $resource = CollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()->using(fn (Collection $record): bool => app(DeleteCatalogRecord::class)->execute($record)),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Collection
    {
        abort_unless($record instanceof Collection, 404);

        return app(SaveCollection::class)->update($record, $data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('products', $data)) {
            /** @var array<int, string>|null $products */
            $products = is_array($data['products'] ?? null) ? $data['products'] : null;
            $data['products'] = OwnerScopedIds::ensureAllowed('products', Product::class, $products);
        }

        return $data;
    }
}
