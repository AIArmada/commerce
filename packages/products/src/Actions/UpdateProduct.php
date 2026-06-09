<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Events\ProductUpdated;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Facades\DB;

final class UpdateProduct
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Product $product, array $data): Product
    {
        DB::transaction(function () use ($product, $data): void {
            $product->update($data);
        });

        $fresh = $product->fresh();

        if ($fresh !== null) {
            ProductUpdated::dispatch($fresh);

            return $fresh;
        }

        return $product;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Product $product, array $data): Product
    {
        return $this->execute($product, $data);
    }
}
