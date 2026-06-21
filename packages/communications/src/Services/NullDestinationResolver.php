<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Data\ResolvedDestinationData;

class NullDestinationResolver implements DestinationResolver
{
    public function resolve(mixed $notifiable, string $channel): ?ResolvedDestinationData
    {
        return null;
    }
}
