<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventRegistration;

interface EventPassIssuer
{
    /** @return iterable<\AIArmada\Events\Models\EventPass> */
    public function issuePassesFor(EventRegistration $registration): iterable;
}
