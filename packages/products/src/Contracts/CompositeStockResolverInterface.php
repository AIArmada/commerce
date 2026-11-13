<?php

declare(strict_types=1);

namespace AIArmada\Products\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CompositeStockResolverInterface
{
    public function availableFor(Model $inventoryable): int;
}
