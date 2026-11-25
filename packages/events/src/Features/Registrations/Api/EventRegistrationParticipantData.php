<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Api;

final readonly class EventRegistrationParticipantData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $registrationId,
        public string $eventId,
        public ?string $occurrenceId,
        public ?string $sessionId,
        public string $morphClass,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public bool $isPrimary,
        public ?string $participantType = null,
        public ?string $participantId = null,
        public ?string $relationshipToRegistrant = null,
        public ?int $age = null,
        public ?string $gender = null,
        public ?string $status = null,
        public ?string $notes = null,
        public ?array $metadata = null,
    ) {}
}
