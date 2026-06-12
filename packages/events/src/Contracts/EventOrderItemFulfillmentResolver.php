<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventRegistrationItem;

interface EventOrderItemFulfillmentResolver
{
    public function resolve(EventRegistrationItem $registrationItem): mixed;
}
