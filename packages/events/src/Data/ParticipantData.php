<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\EventRegistrationParticipant;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class ParticipantData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string|null|Optional $email,
        public readonly string|null|Optional $phone,
        public readonly string|null|Optional $relationship_to_registrant,
        public readonly bool $is_primary,
        public readonly int|null|Optional $age,
        public readonly string|null|Optional $gender,
        public readonly string $status,
        public readonly string|null|Optional $event_occurrence_id,
        public readonly string|null|Optional $event_session_id,
        public readonly CarbonImmutable $created_at,
    ) {}

    public static function fromParticipant(EventRegistrationParticipant $participant): self
    {
        return new self(
            id: $participant->id,
            name: $participant->name,
            email: $participant->email,
            phone: $participant->phone,
            relationship_to_registrant: $participant->relationship_to_registrant,
            is_primary: $participant->is_primary,
            age: $participant->age,
            gender: $participant->gender,
            status: $participant->status,
            event_occurrence_id: $participant->event_occurrence_id,
            event_session_id: $participant->event_session_id,
            created_at: CarbonImmutable::make($participant->created_at),
        );
    }
}
