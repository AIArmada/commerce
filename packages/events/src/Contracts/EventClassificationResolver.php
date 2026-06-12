<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\Event;

interface EventClassificationResolver
{
    public function resolve(Event $event): array;
}
