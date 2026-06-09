<?php

declare(strict_types=1);

namespace AIArmada\Products\Contracts;

use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Collection;

interface VariantGeneratorInterface
{
    /**
     * @return Collection<int, Variant>
     */
    public function generate(Product $product): Collection;
}
