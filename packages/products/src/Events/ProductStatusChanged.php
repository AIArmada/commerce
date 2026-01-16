<?php

declare(strict_types=1);

namespace AIArmada\Products\Events;

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ProductStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Product $product,
        public ProductStatus $oldStatus,
        public ProductStatus $newStatus
    ) {}
}
