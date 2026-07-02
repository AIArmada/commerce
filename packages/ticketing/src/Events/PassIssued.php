<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Events;

use AIArmada\Ticketing\Models\Pass;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PassIssued
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Pass $pass,
        public readonly CarbonImmutable $issuedAt,
    ) {}
}
