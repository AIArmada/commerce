<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use AIArmada\Events\Enums\RegistrationMode;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Ticketing\Enums\PricingMode;

final readonly class EventRegistrationScope
{
    public function __construct(
        public Event $event,
        public ?EventOccurrence $occurrence,
        public ?EventSession $session,
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

    public function toRegistrationData(): array
    {
        return [
            'event_id' => $this->event->id,
            'event_occurrence_id' => $this->occurrence?->id,
            'event_session_id' => $this->session?->id,
        ];
    }

    public function capacityRemaining(): ?int
    {
        $remaining = [];

        if ($this->occurrence !== null) {
            $occurrenceRemaining = $this->occurrence->capacityRemaining();

            if ($occurrenceRemaining !== null) {
                $remaining[] = $occurrenceRemaining;
            }
        }

        if ($this->session !== null) {
            $sessionRemaining = $this->session->capacityRemaining();

            if ($sessionRemaining !== null) {
                $remaining[] = $sessionRemaining;
            }
        }

        if ($remaining === []) {
            return null;
        }

        return min($remaining);
    }
}
