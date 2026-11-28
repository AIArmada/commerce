<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Ticketing\Data\PricingMode;

final readonly class EventRegistrationScopeData
{
    public function __construct(
        public string $eventId,
        public ?string $occurrenceId,
        public ?string $sessionId,
        public PricingMode $pricingMode,
        public RegistrationMode $registrationMode,
        public bool $shouldIssuePasses,
        public ?int $capacity,
    ) {}

    public function isFreeOnly(): bool
    {
        return $this->pricingMode->isFreeOnly();
    }

    public function isOpenDoor(): bool
    {
        return $this->registrationMode->isOpenDoor();
    }

    public function requiresRegistration(): bool
    {
        return $this->registrationMode->isRequired();
    }

    /**
     * @return array{event_id: string, event_occurrence_id: ?string, event_session_id: ?string}
     */
    public function toRegistrationData(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_occurrence_id' => $this->occurrenceId,
            'event_session_id' => $this->sessionId,
        ];
    }
}
