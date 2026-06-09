<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Contracts;

use AIArmada\Pricing\Data\TierPriceResultData;

interface TierResolverInterface
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $tierableType, string $tierableId, int $quantity, array $context): ?TierPriceResultData;
}
