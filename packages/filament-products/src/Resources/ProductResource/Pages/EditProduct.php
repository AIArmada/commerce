<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts\Resources\ProductResource\Pages;

use AIArmada\CommerceSupport\Support\Filament\OwnerScopedIds;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\FilamentProducts\Support\CompositeComponentsFormData;
use AIArmada\Products\Actions\PublishCompositeProduct;
use AIArmada\Products\Actions\ReplaceProductComponents;
use AIArmada\Products\Actions\UpdateProduct as UpdateProductAction;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        if (! $record instanceof Product) {
            return parent::handleRecordUpdate($record, $data);
        }

        /** @var array<array-key, mixed>|null $components */
        $components = is_array($data['components'] ?? null) ? $data['components'] : null;
        unset($data['components']);

        $isComposite = self::resolveType($record) === ProductType::Composite;

        $requestedStatus = $data['status'] ?? $record->status;
        $requestedStatus = $requestedStatus instanceof ProductStatus
            ? $requestedStatus
            : ProductStatus::tryFrom((string) $requestedStatus);

        $publishAfterSave = $isComposite
            && $requestedStatus === ProductStatus::Active
            && $record->status !== ProductStatus::Active;

        if ($publishAfterSave) {
            // Save the composition while the package is still a draft, then
            // publish through the domain action so validation runs last.
            $data['status'] = ProductStatus::Draft->value;
        }

        return DB::transaction(function () use ($record, $data, $components, $isComposite, $publishAfterSave): Product {
            $product = app(UpdateProductAction::class)->execute($record, $data);

            if ($isComposite && $components !== null) {
                app(ReplaceProductComponents::class)->handle(
                    $product,
                    CompositeComponentsFormData::toActionPayload($components),
                );
            }

            if ($publishAfterSave) {
                return app(PublishCompositeProduct::class)->handle($product);
            }

            return $product->fresh() ?? $product;
        });
    }

    private static function resolveType(Product $product): ?ProductType
    {
        return $product->type instanceof ProductType
            ? $product->type
            : ProductType::tryFrom((string) $product->type);
    }
}
