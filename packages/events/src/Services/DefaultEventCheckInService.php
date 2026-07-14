<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Data\EventCheckInResult;
use AIArmada\Events\Events\EventAttendanceCheckedIn;
use AIArmada\Events\Events\EventAttendanceCheckedOut;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttendance;
use AIArmada\Events\Models\EventAttendanceLog;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationParticipant;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Ticketing\Models\Pass;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DefaultEventCheckInService implements EventCheckInService
{
    public function checkIn(array $data): EventAttendance
    {
        return $this->checkInWithResult($data)->attendance;
    }

    public function checkInWithResult(array $data): EventCheckInResult
    {
        $event = EventWriteGuard::findOrFail($data['event_id']);
        $registration = $this->resolveRegistration($event, $data['event_registration_id'] ?? null);
        $participant = $this->resolveParticipant($event, $data['event_registration_participant_id'] ?? null, $registration?->id);

        if ($participant !== null && $registration === null) {
            $registration = $participant->registration;
        }

        $pass = $this->resolvePass($event, $data['pass_id'] ?? null, $registration?->id);

        if ($pass !== null && $registration === null) {
            $registration = $pass->registration instanceof EventRegistration
                ? $pass->registration
                : null;
        }

        $session = $this->resolveSession($event, $data['event_session_id'] ?? null);

        $occurrenceId = $data['event_occurrence_id'] ?? $session?->event_occurrence_id
            ?? $registration?->event_occurrence_id
            ?? $pass?->occurrence_id
            ?? $participant?->registration?->event_occurrence_id
            ?? null;

        $occurrence = $this->resolveOccurrence($event, $occurrenceId);

        if ($session !== null && $session->event_occurrence_id !== $occurrence->id) {
            throw new InvalidArgumentException('The selected session does not belong to the selected event occurrence.');
        }

        if ($registration !== null && $registration->event_occurrence_id !== null && $registration->event_occurrence_id !== $occurrence->id) {
            throw new InvalidArgumentException('The selected registration does not belong to the selected event occurrence.');
        }

        if ($pass !== null && $pass->occurrence_id !== null && $pass->occurrence_id !== $occurrence->id) {
            throw new InvalidArgumentException('The selected pass does not belong to the selected event occurrence.');
        }

        if ($pass !== null
            && $registration !== null
            && ($pass->registration_type !== $registration->getMorphClass()
                || $pass->registration_id !== $registration->id)) {
            throw new InvalidArgumentException('The selected pass does not belong to the selected registration.');
        }

        [$attendance, $wasCreated] = DB::transaction(function () use (
            $data,
            $event,
            $occurrence,
            $participant,
            $pass,
            $registration,
            $session,
        ): array {
            $this->lockCheckInIdentity($pass, $participant, $registration);

            $existing = $this->findActiveAttendance(
                event: $event,
                occurrence: $occurrence,
                session: $session,
                registration: $registration,
                participant: $participant,
                pass: $pass,
                attendeeType: $data['attendee_type'] ?? null,
                attendeeId: $data['attendee_id'] ?? null,
            );

            if ($existing !== null) {
                return [$existing, false];
            }

            $attendanceClass = ModelResolver::attendanceClass();
            $attendance = $attendanceClass::query()->create([
                'event_id' => $event->id,
                'event_occurrence_id' => $occurrence->id,
                'event_session_id' => $session?->id,
                'event_registration_id' => $registration?->id,
                'event_registration_participant_id' => $participant?->id,
                'pass_id' => $pass?->id,
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

            return [$attendance, true];
        });

        if ($wasCreated) {
            event(new EventAttendanceCheckedIn($attendance));
        }

        return new EventCheckInResult($attendance, $wasCreated);
    }

    private function lockCheckInIdentity(
        ?Pass $pass,
        ?EventRegistrationParticipant $participant,
        ?EventRegistration $registration,
    ): void {
        if ($pass !== null) {
            Pass::query()->whereKey($pass->getKey())->lockForUpdate()->firstOrFail();

            return;
        }

        if ($participant !== null) {
            EventRegistrationParticipant::query()
                ->whereKey($participant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return;
        }

        if ($registration !== null) {
            $registrationClass = ModelResolver::registrationClass();
            $registrationClass::query()
                ->whereKey($registration->getKey())
                ->lockForUpdate()
                ->firstOrFail();
        }
    }

    private function findActiveAttendance(
        Event $event,
        EventOccurrence $occurrence,
        ?EventSession $session,
        ?EventRegistration $registration,
        ?EventRegistrationParticipant $participant,
        ?Pass $pass,
        mixed $attendeeType,
        mixed $attendeeId,
    ): ?EventAttendance {
        $attendanceClass = ModelResolver::attendanceClass();
        $query = $attendanceClass::query()
            ->where('event_id', $event->id)
            ->where('event_occurrence_id', $occurrence->id)
            ->where('event_session_id', $session?->id)
            ->whereNull('checked_out_at')
            ->whereNull('cancelled_at');

        if ($pass !== null) {
            return $query->where('pass_id', $pass->id)->first();
        }

        if ($participant !== null) {
            return $query->where('event_registration_participant_id', $participant->id)->first();
        }

        if ($registration !== null) {
            return $query->where('event_registration_id', $registration->id)->first();
        }

        if ($attendeeType !== null && $attendeeId !== null) {
            return $query
                ->where('attendee_type', $attendeeType)
                ->where('attendee_id', $attendeeId)
                ->first();
        }

        return null;
    }

    public function checkOut(EventAttendance $attendance, mixed $actor = null): void
    {
        EventWriteGuard::findOrFail($attendance->event_id);

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
        $event = EventWriteGuard::findOrFail($session->event_id);

        $data['event_session_id'] = $session->id;
        $data['event_occurrence_id'] = $session->event_occurrence_id;
        $data['event_id'] = $event->id;

        if ($passOrRegistration instanceof Pass) {
            if (! $this->passBelongsToEvent($passOrRegistration, $event)) {
                throw new InvalidArgumentException('The selected pass does not belong to the selected event session.');
            }

            $data['pass_id'] = $passOrRegistration->id;
            $data['event_registration_id'] = $passOrRegistration->registration instanceof EventRegistration
                ? $passOrRegistration->registration->id
                : null;
            $data['event_registration_participant_id'] = $this->resolveParticipantFromPass($passOrRegistration)?->id;
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
            throw new InvalidArgumentException('checkInToSession requires a pass, registration, participant, or null.');
        }

        return $this->checkInWithResult($data)->attendance;
    }

    public function cancelCheckIn(EventAttendance $attendance, string $reason, mixed $actor = null): void
    {
        EventWriteGuard::findOrFail($attendance->event_id);

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

        $registrationClass = ModelResolver::registrationClass();
        $registration = $registrationClass::query()
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

    private function resolvePass(Event $event, mixed $passId, ?string $registrationId): ?Pass
    {
        if ($passId === null) {
            return null;
        }

        $registrationClass = ModelResolver::registrationClass();

        $query = Pass::query()
            ->whereKey($passId)
            ->with(['ticketable', 'registration', 'holder']);

        if ($registrationId !== null) {
            $query
                ->where('registration_type', (new $registrationClass)->getMorphClass())
                ->where('registration_id', $registrationId);
        }

        $pass = $query->first();

        if (! $pass instanceof Pass || ! $this->passBelongsToEvent($pass, $event)) {
            throw new InvalidArgumentException('The selected pass does not belong to the selected registration.');
        }

        return $pass;
    }

    private function passBelongsToEvent(Pass $pass, Event $event): bool
    {
        $pass->loadMissing('ticketable', 'registration', 'holder');

        if ($pass->session_id !== null) {
            return EventSession::query()
                ->whereKey($pass->session_id)
                ->where('event_id', $event->id)
                ->exists();
        }

        if ($pass->occurrence_id !== null) {
            return EventOccurrence::query()
                ->whereKey($pass->occurrence_id)
                ->where('event_id', $event->id)
                ->exists();
        }

        $ticketable = $pass->ticketable;

        if ($ticketable instanceof Event) {
            return $ticketable->is($event);
        }

        if ($ticketable instanceof EventOccurrence || $ticketable instanceof EventSession) {
            return $ticketable->event_id === $event->id;
        }

        return $pass->registration instanceof EventRegistration
            && $pass->registration->event_id === $event->id;
    }

    private function resolveParticipantFromPass(Pass $pass): ?EventRegistrationParticipant
    {
        $holder = $pass->holder;

        if ($holder === null || $holder->holder_type === null || $holder->holder_id === null) {
            return null;
        }

        $holderClass = Relation::getMorphedModel($holder->holder_type) ?? $holder->holder_type;

        if ($holderClass !== EventRegistrationParticipant::class) {
            return null;
        }

        return EventRegistrationParticipant::query()->find($holder->holder_id);
    }
}
