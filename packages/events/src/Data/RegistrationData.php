<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\EventRegistration;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class RegistrationData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $registration_no,
        public readonly string $registration_type,
        public readonly string $status,
        public readonly string $source,
        public readonly int $total_participants,
        public readonly int | null | Optional $total_amount,
        public readonly string | null | Optional $currency,
        public readonly string | null | Optional $payment_status,
        public readonly CarbonImmutable | null | Optional $registered_at,
        public readonly CarbonImmutable | null | Optional $approved_at,
        public readonly CarbonImmutable | null | Optional $cancelled_at,
        public readonly string | null | Optional $status_reason,
        public readonly string $event_id,
        public readonly string $event_title,
        public readonly string | null | Optional $event_occurrence_id,
        public readonly string | null | Optional $event_session_id,
        /** @var array<ParticipantData> */
        public readonly array $participants,
        /** @var array<PassData> */
        public readonly array $passes,
    ) {}

    public static function fromRegistration(EventRegistration $registration): self
    {
        $participants = [];
        if ($registration->relationLoaded('participants')) {
            foreach ($registration->participants as $participant) {
                $participants[] = ParticipantData::fromParticipant($participant);
            }
        }

        $passes = [];
        if ($registration->relationLoaded('passes')) {
            foreach ($registration->passes as $pass) {
                $passes[] = PassData::fromPass($pass);
            }
        }

        return new self(
            id: $registration->id,
            registration_no: $registration->registration_no,
            registration_type: $registration->registration_type,
            status: $registration->status,
            source: $registration->source,
            total_participants: $registration->total_participants,
            total_amount: $registration->total_amount,
            currency: $registration->currency,
            payment_status: $registration->payment_status,
            registered_at: $registration->registered_at,
            approved_at: $registration->approved_at,
            cancelled_at: $registration->cancelled_at,
            status_reason: $registration->status_reason,
            event_id: $registration->event_id,
            event_title: $registration->event?->title ?? '',
            event_occurrence_id: $registration->event_occurrence_id,
            event_session_id: $registration->event_session_id,
            participants: $participants,
            passes: $passes,
        );
    }
}
