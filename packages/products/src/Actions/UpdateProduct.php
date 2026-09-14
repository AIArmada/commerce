<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Models\Product;
use Illuminate\Support\Facades\DB;

final class UpdateProduct
{
    /**
     * ProductUpdated fires once via the model's $dispatchesEvents mapping.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(Product $product, array $data): Product
    {
        DB::transaction(function () use ($product, $data): void {
            $product->update($data);
        });

        return $product->fresh() ?? $product;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Product $product, array $data): Product
    {
        return $this->execute($product, $data);
    }
}
