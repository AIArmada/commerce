<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

final readonly class EventNotificationRecipientData
{
    public function __construct(
        public string $notifiableType,
        public string $notifiableId,
    ) {}
}
