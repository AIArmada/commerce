<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Api;

final readonly class EventRegistrationOrderItemRef
{
    public function __construct(
        public string $id,
        public string $registrationId,
        public string $eventId,
        public ?string $ticketTypeId,
        public ?string $externalOrderItemId,
        public ?string $externalOrderItemType,
        public int $quantity,
    ) {}
}
