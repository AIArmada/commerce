<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

use AIArmada\Communications\Data\ResolvedDestinationData;

interface DestinationResolver
{
    public function resolve(mixed $notifiable, string $channel): ?ResolvedDestinationData;
}
