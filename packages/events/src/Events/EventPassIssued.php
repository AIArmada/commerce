<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

use AIArmada\Events\Models\EventPass;

final class EventPassIssued
{
    public function __construct(
        public EventPass $pass,
    ) {}
}
