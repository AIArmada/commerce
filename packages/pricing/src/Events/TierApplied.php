<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Events;

use AIArmada\Pricing\Data\TierPriceResultData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TierApplied
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $tierableType,
        public string $tierableId,
        public int $quantity,
        public TierPriceResultData $result,
        /** @var array<string, mixed> */
        public array $context,
    ) {}
}
