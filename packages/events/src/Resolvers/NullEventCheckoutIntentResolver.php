<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventSession;

final class NullEventCheckoutIntentResolver implements EventCheckoutIntentResolver
{
    public function resolve(EventOccurrence | EventSession $target, EventRegistration $registration): mixed
    {
        return null;
    }
}
