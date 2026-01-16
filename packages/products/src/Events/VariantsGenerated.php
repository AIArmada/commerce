<?php

declare(strict_types=1);

namespace AIArmada\Products\Events;

use AIArmada\Products\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

final class VariantsGenerated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Collection<int, \AIArmada\Products\Models\Variant>  $variants
     */
    public function __construct(
        public Product $product,
        public Collection $variants
    ) {}
}
