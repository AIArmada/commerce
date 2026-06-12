<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventSearchPayloadResolver;

final class DefaultEventSearchPayloadResolver implements EventSearchPayloadResolver
{
    public function resolve(array $payload): array
    {
        return $payload;
    }
}
