<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\Event;

interface EventSearchPayloadResolver
{
    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(Event $event): array;
}
