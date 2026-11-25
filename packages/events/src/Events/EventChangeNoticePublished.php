<?php

declare(strict_types=1);

namespace AIArmada\Events\Events;

final readonly class EventChangeNoticePublished
{
    public function __construct(
        public string $changeLogId,
        public string $eventId,
        public ?string $updateId = null,
    ) {}
}
