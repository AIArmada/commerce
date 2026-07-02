<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Events;

use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PassTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Pass $pass,
        public readonly PassHolder $previousHolder,
        public readonly PassHolder $newHolder,
        public readonly ?string $reason = null,
        public readonly CarbonImmutable $transferredAt = new CarbonImmutable,
    ) {}
}
