<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;

interface EventCheckoutIntentResolver
{
    public function resolve(EventOccurrence | EventSession $target, EventRegistration $registration): mixed;
}
