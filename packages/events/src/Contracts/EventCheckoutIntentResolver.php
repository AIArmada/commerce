<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;

interface EventCheckoutIntentResolver
{
    public function resolve(EventOccurrence $occurrence, EventRegistration $registration): mixed;
}
