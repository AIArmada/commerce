<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventSessionUpdated
{
    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function __construct(
        public string $sessionId,
        public string $eventId,
        public array $changes = [],
    ) {}
}
