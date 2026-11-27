<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCompositeProduct
{
    public function __construct(
        private UpdateProduct $update,
        private ReplaceProductComponents $replace,
        private PublishCompositeProduct $publish,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>>|null $components
     */
    public function execute(Product $record, array $data, ?array $components): Product
    {
        return DB::transaction(function () use ($record, $data, $components): Product {
            $query = Product::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $product = $query->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $isComposite = $product->type === ProductType::Composite;
            $requested = $data['status'] ?? $product->status;
            $status = $requested instanceof ProductStatus ? $requested : ProductStatus::tryFrom((string) $requested);
            $publish = $isComposite && $status === ProductStatus::Active && $product->status !== ProductStatus::Active;

            if ($publish) {
                $data['status'] = ProductStatus::Draft->value;
            }

            $product = $this->update->execute($product, $data);

            if ($isComposite && $components !== null) {
                $this->replace->handle($product, $components);
            }

            return $publish ? $this->publish->handle($product) : ($product->fresh() ?? $product);
        });
    }
}
