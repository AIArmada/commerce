<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Events;

use AIArmada\Ticketing\Data\PassData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

final class PassIssued
{
    use Dispatchable;

    public function __construct(
        public readonly PassData $pass,
        public readonly CarbonImmutable $issuedAt,
    ) {}
}
