<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Models\EventRegistration;

interface EventPassIssuer
{
    /** @return iterable<EventPass> */
    public function issuePassesFor(EventRegistration $registration): iterable;
}
