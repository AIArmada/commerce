<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Events;

use AIArmada\Pricing\Contracts\Priceable;
use AIArmada\Pricing\Data\PriceResultData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PriceCalculated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Priceable $item,
        public PriceResultData $result,
        public int $quantity,
        /** @var array<string, mixed> */
        public array $context,
    ) {}
}
