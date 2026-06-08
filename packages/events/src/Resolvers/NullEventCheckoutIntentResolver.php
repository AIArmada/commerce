<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventCheckoutIntentResolver;
use AIArmada\Events\Data\EventCheckoutIntentData;
use AIArmada\Events\Models\Occurrence;

final class NullEventCheckoutIntentResolver implements EventCheckoutIntentResolver
{
    public function resolve(Occurrence $occurrence, int $quantity = 1, array $metadata = []): ?EventCheckoutIntentData
    {
        return null;
    }
}
