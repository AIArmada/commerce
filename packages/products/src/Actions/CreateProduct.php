<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\Products\Models\Product;
use Illuminate\Support\Facades\DB;

final class CreateProduct
{
    /**
     * ProductCreated fires once via the model's $dispatchesEvents mapping.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): Product
    {
        return DB::transaction(fn (): Product => Product::create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): Product
    {
        return $this->execute($attributes);
    }
}
