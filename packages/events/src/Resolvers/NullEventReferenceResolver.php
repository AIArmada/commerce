<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventReferenceResolver;
use AIArmada\Events\Models\EventReference;

final class NullEventReferenceResolver implements EventReferenceResolver
{
    public function resolve(EventReference $reference): array
    {
        return [];
    }
}
