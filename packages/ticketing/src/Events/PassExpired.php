<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Events;

use AIArmada\Ticketing\Data\PassData;
use Illuminate\Foundation\Events\Dispatchable;

final class PassExpired
{
    use Dispatchable;

    public function __construct(
        public readonly PassData $pass,
    ) {}
}
