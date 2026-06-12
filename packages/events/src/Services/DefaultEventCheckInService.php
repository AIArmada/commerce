<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Events\EventAttendanceCheckedIn;
use AIArmada\Events\Events\EventAttendanceCheckedOut;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttendance;
use AIArmada\Events\Models\EventAttendanceLog;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationParticipant;
use AIArmada\Events\Models\EventSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class DefaultEventCheckInService implements EventCheckInService
{
    public function checkIn(array $data): EventAttendance
    {
        $event = OwnerWriteGuard::findOrFailForOwner(Event::class, $data['event_id']);
        $registration = $this->resolveRegistration($event, $data['event_registration_id'] ?? null);
        $participant = $this->resolveParticipant($event, $data['event_registration_participant_id'] ?? null, $registration?->id);

        if ($participant !== null && $registration === null) {
            $registration = $participant->registration;
        }

        $pass = $this->resolvePass($event, $data['event_pass_id'] ?? null, $registration?->id);

        if ($pass !== null && $registration === null) {
            $registration = $pass->registration;
        }

        $session = $this->resolveSession($event, $data['event_session_id'] ?? null);

        $occurrenceId = $data['event_occurrence_id'] ?? $session?->event_occurrence_id
            ?? $registration?->event_occurrence_id
            ?? $pass?->event_occurrence_id
            ?? $participant?->registration?->event_occurrence_id
            ?? null;

        $occurrence = $this->resolveOccurrence($event, $occurrenceId);

        if ($session !== null && $session->event_occurrence_id !== $occurrence->id) {
            throw new InvalidArgumentException('The selected session does not belong to the selected event occurrence.');
        }

        if ($registration !== null && $registration->event_occurrence_id !== null && $registration->event_occurrence_id !== $occurrence->id) {
            throw new InvalidArgumentException('The selected registration does not belong to the selected event occurrence.');
        }

        if ($pass !== null && $pass->event_occurrence_id !== null && $pass->event_occurrence_id !== $occurrence->id) {
            throw new InvalidArgumentException('The selected pass does not belong to the selected event occurrence.');
        }

        if ($pass !== null && $registration !== null && $pass->event_registration_id !== $registration->id) {
            throw new InvalidArgumentException('The selected pass does not belong to the selected registration.');
        }

        $attendance = EventAttendance::query()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session?->id,
            'event_registration_id' => $registration?->id,
            'event_registration_participant_id' => $participant?->id,
            'event_pass_id' => $pass?->id,
            'attendee_type' => $data['attendee_type'] ?? null,
            'attendee_id' => $data['attendee_id'] ?? null,
            'attendance_type' => $data['attendance_type'] ?? 'registered',
            'checked_in_at' => CarbonImmutable::now(),
            'check_in_source' => $data['check_in_source'] ?? 'manual',
            'notes' => $data['notes'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        EventAttendanceLog::query()->create([
            'event_attendance_id' => $attendance->id,
            'action' => 'checked_in',
            'source' => $data['check_in_source'] ?? 'manual',
            'occurred_at' => CarbonImmutable::now(),
        ]);

        event(new EventAttendanceCheckedIn($attendance));

        return $attendance;
    }

    public function checkOut(EventAttendance $attendance, mixed $actor = null): void
    {
        OwnerWriteGuard::findOrFailForOwner(Event::class, $attendance->event_id);

        $attendance->update(['checked_out_at' => CarbonImmutable::now()]);

        EventAttendanceLog::query()->create([
            'event_attendance_id' => $attendance->id,
            'action' => 'checked_out',
            'source' => $attendance->check_in_source ?? 'manual',
            'performed_by_type' => $actor instanceof Model ? $actor->getMorphClass() : null,
            'performed_by_id' => $actor instanceof Model ? $actor->getKey() : null,
            'occurred_at' => CarbonImmutable::now(),
        ]);

        event(new EventAttendanceCheckedOut($attendance));
    }

    public function checkInToSession(mixed $passOrRegistration, EventSession $session, array $data = []): EventAttendance
    {
        $event = OwnerWriteGuard::findOrFailForOwner(Event::class, $session->event_id);

        $data['event_session_id'] = $session->id;
        $data['event_occurrence_id'] = $session->event_occurrence_id;
        $data['event_id'] = $event->id;

        if ($passOrRegistration instanceof EventPass) {
            if ($passOrRegistration->event_id !== $event->id) {
                throw new InvalidArgumentException('The selected pass does not belong to the selected event session.');
            }

            $data['event_pass_id'] = $passOrRegistration->id;
            $data['event_registration_id'] = $passOrRegistration->event_registration_id;
            $data['event_registration_participant_id'] = $passOrRegistration->event_registration_participant_id;
        } elseif ($passOrRegistration instanceof EventRegistration) {
            if ($passOrRegistration->event_id !== $event->id) {
                throw new InvalidArgumentException('The selected registration does not belong to the selected event session.');
            }

            $data['event_registration_id'] = $passOrRegistration->id;
        } elseif ($passOrRegistration instanceof EventRegistrationParticipant) {
            if ($passOrRegistration->event_id !== $event->id) {
                throw new InvalidArgumentException('The selected participant does not belong to the selected event session.');
            }

            $data['event_registration_participant_id'] = $passOrRegistration->id;
            $data['event_registration_id'] = $passOrRegistration->event_registration_id;
        } elseif ($passOrRegistration !== null) {
            throw new InvalidArgumentException('checkInToSession requires an event pass, registration, participant, or null.');
        }

        return $this->checkIn($data);
    }

    public function cancelCheckIn(EventAttendance $attendance, string $reason, mixed $actor = null): void
    {
        OwnerWriteGuard::findOrFailForOwner(Event::class, $attendance->event_id);

        $attendance->update([
            'cancelled_at' => CarbonImmutable::now(),
            'notes' => $reason,
        ]);

        EventAttendanceLog::query()->create([
            'event_attendance_id' => $attendance->id,
            'action' => 'cancelled_check_in',
            'source' => $attendance->check_in_source ?? 'manual',
            'performed_by_type' => $actor instanceof Model ? $actor->getMorphClass() : null,
            'performed_by_id' => $actor instanceof Model ? $actor->getKey() : null,
            'occurred_at' => CarbonImmutable::now(),
            'notes' => $reason,
        ]);
    }

    private function resolveOccurrence(Event $event, mixed $occurrenceId): EventOccurrence
    {
        if ($occurrenceId === null) {
            $occurrence = EventOccurrence::query()
                ->where('event_id', $event->id)
                ->whereIn('status', ['published', 'scheduled'])
                ->orderBy('starts_at')
                ->first();

            if ($occurrence !== null) {
                return $occurrence;
            }

            throw new InvalidArgumentException('An event occurrence is required for check-in.');
        }

        $occurrence = EventOccurrence::query()
            ->whereKey($occurrenceId)
            ->where('event_id', $event->id)
            ->first();

        if ($occurrence === null) {
            throw new InvalidArgumentException('The selected occurrence does not belong to the selected event.');
        }

        return $occurrence;
    }

    private function resolveSession(Event $event, mixed $sessionId): ?EventSession
    {
        if ($sessionId === null) {
            return null;
        }

        $session = EventSession::query()
            ->whereKey($sessionId)
            ->where('event_id', $event->id)
            ->first();

        if ($session === null) {
            throw new InvalidArgumentException('The selected session does not belong to the selected event.');
        }

        return $session;
    }

    private function resolveRegistration(Event $event, mixed $registrationId): ?EventRegistration
    {
        if ($registrationId === null) {
            return null;
        }

        $registration = EventRegistration::query()
            ->whereKey($registrationId)
            ->where('event_id', $event->id)
            ->first();

        if ($registration === null) {
            throw new InvalidArgumentException('The selected registration does not belong to the selected event.');
        }

        return $registration;
    }

    private function resolveParticipant(Event $event, mixed $participantId, ?string $registrationId): ?EventRegistrationParticipant
    {
        if ($participantId === null) {
            return null;
        }

        $query = EventRegistrationParticipant::query()
            ->whereKey($participantId)
            ->where('event_id', $event->id);

        if ($registrationId !== null) {
            $query->where('event_registration_id', $registrationId);
        }

        $participant = $query->first();

        if ($participant === null) {
            throw new InvalidArgumentException('The selected participant does not belong to the selected registration.');
        }

        return $participant;
    }

    private function resolvePass(Event $event, mixed $passId, ?string $registrationId): ?EventPass
    {
        if ($passId === null) {
            return null;
        }

        $query = EventPass::query()
            ->whereKey($passId)
            ->where('event_id', $event->id);

        if ($registrationId !== null) {
            $query->where('event_registration_id', $registrationId);
        }

        $pass = $query->first();

        if ($pass === null) {
            throw new InvalidArgumentException('The selected pass does not belong to the selected registration.');
        }

        return $pass;
    }
}
