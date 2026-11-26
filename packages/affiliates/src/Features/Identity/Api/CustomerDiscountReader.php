<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface CustomerDiscountReader
{
    public function find(string $affiliateId): ?CustomerDiscount;
}
