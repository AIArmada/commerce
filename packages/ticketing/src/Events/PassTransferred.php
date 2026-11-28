<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Events;

use AIArmada\Ticketing\Data\PassTransferData;
use Illuminate\Foundation\Events\Dispatchable;

final class PassTransferred
{
    use Dispatchable;

    public function __construct(
        public readonly PassTransferData $transfer,
    ) {}
}
