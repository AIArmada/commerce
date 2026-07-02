<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Events;

use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PassesBulkTransferred
{
    use Dispatchable, SerializesModels;

    /** @param Collection<int, Pass> $passes */
    public function __construct(
        public readonly Collection $passes,
        public readonly ?PassHolder $toHolder = null,
        public readonly ?string $reason = null,
        public readonly CarbonImmutable $transferredAt = new CarbonImmutable,
    ) {}
}
