<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventOrderItemFulfillmentResolver;
use AIArmada\Events\Models\EventRegistrationItem;

final class DefaultEventOrderItemFulfillmentResolver implements EventOrderItemFulfillmentResolver
{
    public function resolve(EventRegistrationItem $registrationItem): mixed
    {
        return null;
    }
}
