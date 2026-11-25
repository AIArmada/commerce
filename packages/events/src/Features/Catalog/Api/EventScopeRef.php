<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Api;

final readonly class EventScopeRef
{
    public function __construct(
        public string $eventId,
        public ?string $occurrenceId = null,
        public ?string $sessionId = null,
    ) {}
}
