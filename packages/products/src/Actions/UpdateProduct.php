<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProduct
{
    /**
     * ProductUpdated fires once via the model's $dispatchesEvents mapping.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $query = Product::query();

            if (config('products.features.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                OwnerContext::assertResolvedOrExplicitGlobal($owner);
                $query->forOwner($owner, false);
            }

            $locked = $query->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $locked->update($data);

            return $locked->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Product $product, array $data): Product
    {
        return $this->execute($product, $data);
    }
}
