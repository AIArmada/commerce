<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Actions;

use AIArmada\Pricing\Contracts\Priceable;

final class ResolveBasePrice
{
    public function resolve(Priceable $item): int
    {
        return $item->getBasePrice();
    }
}
