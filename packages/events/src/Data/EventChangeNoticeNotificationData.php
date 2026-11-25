<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventChangeNoticeNotificationData
{
    /**
     * @param  list<EventNotificationRecipientData>  $recipients
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public string $title,
        public ?string $message,
        public string $eventId,
        public ?string $eventType = null,
        public ?string $changeLogId = null,
        public ?string $updateId = null,
        public string $audienceScope = 'registrants',
        public array $recipients = [],
        public array $metadata = [],
    ) {}
}
