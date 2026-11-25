<?php

declare(strict_types=1);

namespace AIArmada\Events\Drivers;

use AIArmada\Events\Contracts\EventSearchPayloadResolver;

final class DefaultEventSearchPayloadDriver implements EventSearchPayloadResolver
{
    public function resolve(array $payload): array
    {
        return $payload;
    }
}
