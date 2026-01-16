<?php

declare(strict_types=1);

namespace AIArmada\Products\Events;

use AIArmada\Products\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ProductDeleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Product $product
    ) {}
}
