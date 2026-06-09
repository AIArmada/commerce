<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Events\ProductCreated;
use AIArmada\Products\Models\Product;
use Illuminate\Support\Facades\DB;

final class CreateProduct
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): Product
    {
        $product = DB::transaction(fn (): Product => Product::create($attributes));

        ProductCreated::dispatch($product);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): Product
    {
        return $this->execute($attributes);
    }
}
