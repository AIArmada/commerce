<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Events;

use AIArmada\Ticketing\Data\PassTransferData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

final class PassesBulkTransferred
{
    use Dispatchable;

    /** @param list<PassTransferData> $transfers */
    public function __construct(
        public readonly array $transfers,
        public readonly CarbonImmutable $transferredAt = new CarbonImmutable,
    ) {}
}
