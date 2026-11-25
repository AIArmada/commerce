<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventWelcomeNotificationData
{
    public function __construct(
        public string $registrationId,
        public string $registrationNo,
        public string $eventName,
        public string $brandName,
        public string $fromAddress,
        public string $fromName,
        public ?string $ownerType = null,
        public ?string $ownerId = null,
    ) {}
}
