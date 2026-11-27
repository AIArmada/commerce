<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Attendance\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventCheckInService;
use AIArmada\Events\Data\AttendanceRecord;
use AIArmada\Events\Data\EventCheckInResult;
use AIArmada\Events\Events\EventAttendanceCheckedIn;
use AIArmada\Events\Events\EventAttendanceCheckedOut;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Features\Attendance\Models\EventAttendanceLog;
use AIArmada\Events\Features\Registrations\Api\EventRegistrationParticipantData;
use AIArmada\Events\Features\Registrations\Api\RegistrationsApi;
use AIArmada\Events\Features\Registrations\Api\RegistrationSummary;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\LifecyclePolicy;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Ticketing\Models\Pass;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DefaultEventCheckInService implements EventCheckInService
{
    public function __construct(
        private readonly RegistrationsApi $registrations,
        private readonly LifecyclePolicy $lifecycle,
    ) {}

    public function checkIn(array $data): AttendanceRecord
    {
        return $this->checkInWithResult($data)->attendance;
    }

    public function checkInWithResult(array $data): EventCheckInResult
    {
        if (($data['event_id'] ?? null) === null || $data['event_id'] === '') {
            throw new InvalidArgumentException('An event_id is required for check-in.');
        }

        $eventId = (string) $data['event_id'];
        EventWriteGuard::findOrFail($eventId);
        $this->validateCheckInData($data);

        $registration = $this->resolveRegistration($eventId, $data['event_registration_id'] ?? null);
        $participant = $this->resolveParticipant($eventId, $data['event_registration_participant_id'] ?? null, $registration);

        if ($registration === null && $participant !== null) {
            $registration = $this->registrations->summarize($participant->registrationId);

            if ($registration === null || $registration->eventId !== $eventId) {
                throw new InvalidArgumentException('The selected participant does not belong to the selected event.');
            }
        }

        if ($registration !== null && ! $this->lifecycle->canCheckInRegistration($registration->status)) {
            throw new InvalidArgumentException(sprintf(
                'Registration status [%s] is not eligible for check-in.',
                $registration->status,
            ));
        }

        $pass = $this->resolvePass($eventId, $data['pass_id'] ?? null, $registration);
        $registrationType = $this->registrations->registrationMorphClass();

        if ($registration === null
            && $pass !== null
            && $pass->registration_type === $registrationType
            && $pass->registration_id !== null) {
            $registration = $this->registrations->summarize((string) $pass->registration_id);

            if ($registration === null || $registration->eventId !== $eventId) {
                throw new InvalidArgumentException('The selected pass does not belong to the selected event.');
            }

            if (! $this->lifecycle->canCheckInRegistration($registration->status)) {
                throw new InvalidArgumentException(sprintf(
                    'Registration status [%s] is not eligible for check-in.',
                    $registration->status,
                ));
            }
        }

        if ($pass !== null && ! $pass->isValid()) {
            throw new InvalidArgumentException('The selected pass is not valid for check-in.');
        }

        $session = $this->resolveSession($eventId, $data['event_session_id'] ?? null);

        $occurrenceId = $data['event_occurrence_id']
            ?? $session?->getAttribute('event_occurrence_id')
            ?? $registration?->occurrenceId
            ?? $pass?->occurrence_id
            ?? $participant?->occurrenceId
            ?? null;

        $occurrenceId = $occurrenceId !== null ? (string) $occurrenceId : null;
        $occurrenceId = $this->resolveOccurrenceId($eventId, $occurrenceId);

        if ($session !== null && (string) $session->getAttribute('event_occurrence_id') !== $occurrenceId) {
            throw new InvalidArgumentException('The selected session does not belong to the selected event occurrence.');
        }

        if ($registration?->occurrenceId !== null && $registration->occurrenceId !== $occurrenceId) {
            throw new InvalidArgumentException('The selected registration does not belong to the selected event occurrence.');
        }

        if ($pass !== null && $pass->occurrence_id !== null && (string) $pass->occurrence_id !== $occurrenceId) {
            throw new InvalidArgumentException('The selected pass does not belong to the selected event occurrence.');
        }

        if ($pass !== null
            && $registration !== null
            && ($pass->registration_type !== $registrationType
                || (string) $pass->registration_id !== $registration->id)) {
            throw new InvalidArgumentException('The selected pass does not belong to the selected registration.');
        }

        [$attendance, $wasCreated] = DB::transaction(function () use (
            $data,
            $eventId,
            $occurrenceId,
            $participant,
            $pass,
            $registration,
            $session,
        ): array {
            $this->lockCheckInIdentity(
                $pass,
                $participant,
                $registration,
                $data['attendee_type'] ?? null,
                $data['attendee_id'] ?? null,
            );

            $existing = $this->findActiveAttendance(
                eventId: $eventId,
                occurrenceId: $occurrenceId,
                sessionId: $session !== null ? (string) $session->getKey() : null,
                registrationId: $registration?->id,
                participantId: $participant?->id,
                passId: $pass !== null ? (string) $pass->getKey() : null,
                attendeeType: $data['attendee_type'] ?? null,
                attendeeId: $data['attendee_id'] ?? null,
            );

            if ($existing !== null) {
                return [$existing, false];
            }

            $attendanceClass = ModelResolver::attendanceClass();

            /** @var Model $attendance */
            $attendance = $attendanceClass::query()->create([
                'event_id' => $eventId,
                'event_occurrence_id' => $occurrenceId,
                'event_session_id' => $session !== null ? (string) $session->getKey() : null,
                'event_registration_id' => $registration?->id,
                'event_registration_participant_id' => $participant?->id,
                'pass_id' => $pass !== null ? (string) $pass->getKey() : null,
                'attendee_type' => $data['attendee_type'] ?? null,
                'attendee_id' => $data['attendee_id'] ?? null,
                'attendance_type' => $data['attendance_type'] ?? 'registered',
                'checked_in_at' => CarbonImmutable::now(),
                'check_in_source' => $data['check_in_source'] ?? 'manual',
                'verified_by_user_id' => $data['verified_by_user_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            EventAttendanceLog::query()->create([
                'event_attendance_id' => $attendance->getKey(),
                'action' => 'checked_in',
                'source' => $data['check_in_source'] ?? 'manual',
                'performed_by_type' => $data['performed_by_type'] ?? null,
                'performed_by_id' => $data['performed_by_id'] ?? null,
                'occurred_at' => CarbonImmutable::now(),
            ]);

            return [$attendance, true];
        });

        $record = $this->record($attendance);

        if ($wasCreated) {
            $owner = OwnerContext::resolve();

            DB::afterCommit(static function () use ($owner, $record): void {
                OwnerContext::withOwner($owner, static function () use ($record): void {
                    event(new EventAttendanceCheckedIn($record));

                    if ($record->registrationId !== null) {
                        event(new RegistrationCheckedIn($record));
                    }
                });
            });
        }

        return new EventCheckInResult($record, $wasCreated);
    }

    public function checkOut(string $attendanceId, mixed $actor = null): void
    {
        $attendance = DB::transaction(function () use ($attendanceId, $actor): Model {
            $attendanceClass = ModelResolver::attendanceClass();

            /** @var Model $attendance */
            $attendance = $attendanceClass::query()
                ->whereKey($attendanceId)
                ->lockForUpdate()
                ->firstOrFail();

            EventWriteGuard::findOrFail((string) $attendance->getAttribute('event_id'));

            $attendance->update(['checked_out_at' => CarbonImmutable::now()]);

            EventAttendanceLog::query()->create([
                'event_attendance_id' => $attendance->getKey(),
                'action' => 'checked_out',
                'source' => $attendance->getAttribute('check_in_source') ?? 'manual',
                'performed_by_type' => $actor instanceof Model ? $actor->getMorphClass() : null,
                'performed_by_id' => $actor instanceof Model ? $actor->getKey() : null,
                'occurred_at' => CarbonImmutable::now(),
            ]);

            return $attendance;
        });

        event(new EventAttendanceCheckedOut($this->record($attendance)));
    }

    public function cancelCheckIn(string $attendanceId, string $reason, mixed $actor = null): void
    {
        DB::transaction(function () use ($attendanceId, $reason, $actor): void {
            $attendanceClass = ModelResolver::attendanceClass();

            /** @var Model $attendance */
            $attendance = $attendanceClass::query()
                ->whereKey($attendanceId)
                ->lockForUpdate()
                ->firstOrFail();

            EventWriteGuard::findOrFail((string) $attendance->getAttribute('event_id'));

            $attendance->update([
                'cancelled_at' => CarbonImmutable::now(),
                'notes' => $reason,
            ]);

            EventAttendanceLog::query()->create([
                'event_attendance_id' => $attendance->getKey(),
                'action' => 'cancelled_check_in',
                'source' => $attendance->getAttribute('check_in_source') ?? 'manual',
                'performed_by_type' => $actor instanceof Model ? $actor->getMorphClass() : null,
                'performed_by_id' => $actor instanceof Model ? $actor->getKey() : null,
                'occurred_at' => CarbonImmutable::now(),
                'notes' => $reason,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateCheckInData(array $data): void
    {
        $attendeeType = $data['attendee_type'] ?? null;
        $attendeeId = $data['attendee_id'] ?? null;

        if ($attendeeType === null xor $attendeeId === null) {
            throw new InvalidArgumentException('The attendee type and id must both be present or both be null.');
        }

        if ($attendeeType !== null) {
            $attendeeClass = $this->resolveModelClass($attendeeType, 'attendee_type');

            if (! is_string($attendeeId) || $attendeeId === '') {
                throw new InvalidArgumentException('The attendee id must be a non-empty string.');
            }

            if (! $attendeeClass::query()->whereKey($attendeeId)->exists()) {
                throw new InvalidArgumentException('The selected attendee does not exist.');
            }
        }

        $this->assertNullOrNonEmptyString($data['attendance_type'] ?? null, 'attendance_type', 64);
        $this->assertNullOrNonEmptyString($data['check_in_source'] ?? null, 'check_in_source', 64);

        $verifiedBy = $data['verified_by_user_id'] ?? null;

        if ($verifiedBy !== null && (! is_string($verifiedBy) || ! Str::isUuid($verifiedBy))) {
            throw new InvalidArgumentException('The verified_by_user_id must be a valid UUID.');
        }

        if (array_key_exists('notes', $data) && $data['notes'] !== null) {
            if (! is_string($data['notes'])) {
                throw new InvalidArgumentException('Check-in notes must be a string.');
            }

            if (mb_strlen($data['notes']) > 5000) {
                throw new InvalidArgumentException('Check-in notes may not exceed 5000 characters.');
            }
        }

        if (array_key_exists('metadata', $data) && $data['metadata'] !== null && ! is_array($data['metadata'])) {
            throw new InvalidArgumentException('Check-in metadata must be an array.');
        }

        $performerType = $data['performed_by_type'] ?? null;
        $performerId = $data['performed_by_id'] ?? null;

        if ($performerType === null xor $performerId === null) {
            throw new InvalidArgumentException('The performed_by type and id must both be present or both be null.');
        }

        // Performer types stay opaque strings: hosts record staff actors under
        // short morph aliases that this package cannot resolve.
        if ($performerType !== null && (! is_string($performerType) || mb_trim($performerType) === '' || mb_strlen($performerType) > 255)) {
            throw new InvalidArgumentException('The performed_by type must be a non-empty string.');
        }

        if ($performerType !== null) {
            if (! is_string($performerId) && ! is_int($performerId)) {
                throw new InvalidArgumentException('The performed_by id must be a string or integer.');
            }

            if ((string) $performerId === '') {
                throw new InvalidArgumentException('The performed_by id must not be empty.');
            }
        }
    }

    /**
     * @return class-string<Model>
     */
    private function resolveModelClass(mixed $type, string $field): string
    {
        if (! is_string($type) || $type === '') {
            throw new InvalidArgumentException(sprintf('The %s must be a model class name.', $field));
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            throw new InvalidArgumentException(sprintf('The %s must reference an Eloquent model.', $field));
        }

        return $class;
    }

    private function assertNullOrNonEmptyString(mixed $value, string $field, int $maxLength): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || mb_trim($value) === '') {
            throw new InvalidArgumentException(sprintf('The %s must be a non-empty string.', $field));
        }

        if (mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException(sprintf('The %s may not exceed %d characters.', $field, $maxLength));
        }
    }

    private function lockCheckInIdentity(
        ?Pass $pass,
        ?EventRegistrationParticipantData $participant,
        ?RegistrationSummary $registration,
        mixed $attendeeType = null,
        mixed $attendeeId = null,
    ): void {
        if ($pass !== null) {
            Pass::query()->whereKey($pass->getKey())->lockForUpdate()->firstOrFail();

            return;
        }

        if ($participant !== null) {
            $participantClass = ModelResolver::registrationParticipantClass();
            $participantClass::query()->whereKey($participant->id)->lockForUpdate()->firstOrFail();

            return;
        }

        if ($registration !== null) {
            $registrationClass = ModelResolver::registrationClass();
            $registrationClass::query()->whereKey($registration->id)->lockForUpdate()->firstOrFail();

            return;
        }

        if (is_string($attendeeType) && $attendeeType !== '' && $attendeeId !== null) {
            $attendeeClass = $this->resolveModelClass($attendeeType, 'attendee_type');
            $attendeeClass::query()->whereKey($attendeeId)->lockForUpdate()->firstOrFail();
        }
    }

    private function findActiveAttendance(
        string $eventId,
        string $occurrenceId,
        ?string $sessionId,
        ?string $registrationId,
        ?string $participantId,
        ?string $passId,
        mixed $attendeeType,
        mixed $attendeeId,
    ): ?Model {
        if ($registrationId === null
            && $participantId === null
            && $passId === null
            && $attendeeType === null
            && $attendeeId === null) {
            return null;
        }

        $attendanceClass = ModelResolver::attendanceClass();

        /** @var Model|null $attendance */
        $attendance = $attendanceClass::query()
            ->where('event_id', $eventId)
            ->where('event_occurrence_id', $occurrenceId)
            ->when($sessionId !== null, fn ($query) => $query->where('event_session_id', $sessionId), fn ($query) => $query->whereNull('event_session_id'))
            ->whereNull('checked_out_at')
            ->whereNull('cancelled_at')
            ->when($passId !== null, fn ($query) => $query->where('pass_id', $passId))
            ->when($passId === null && $participantId !== null, fn ($query) => $query->where('event_registration_participant_id', $participantId))
            ->when($passId === null && $participantId === null && $registrationId !== null, fn ($query) => $query->where('event_registration_id', $registrationId))
            ->when(
                $passId === null && $participantId === null && $registrationId === null && $attendeeType !== null && $attendeeId !== null,
                fn ($query) => $query->where('attendee_type', $attendeeType)->where('attendee_id', $attendeeId),
            )
            ->first();

        return $attendance;
    }

    private function resolveOccurrenceId(string $eventId, ?string $occurrenceId): string
    {
        $occurrenceClass = ModelResolver::occurrenceClass();

        if ($occurrenceId === null) {
            $statuses = config(
                'events.lifecycle.occurrence.check_in_accepting_statuses',
                ['scheduled', 'published', 'live'],
            );

            /** @var Model|null $occurrence */
            $occurrence = $occurrenceClass::query()
                ->where('event_id', $eventId)
                ->whereIn('status', $statuses)
                ->orderBy('starts_at')
                ->first();

            if ($occurrence === null) {
                throw new InvalidArgumentException('An event occurrence is required for check-in.');
            }

            return (string) $occurrence->getKey();
        }

        /** @var Model|null $occurrence */
        $occurrence = $occurrenceClass::query()
            ->whereKey($occurrenceId)
            ->where('event_id', $eventId)
            ->first();

        if (! $occurrence instanceof Model) {
            throw new InvalidArgumentException('The selected occurrence does not belong to the selected event.');
        }

        if (! $this->lifecycle->canCheckIn($this->statusValue($occurrence))) {
            throw new InvalidArgumentException('The selected occurrence is not accepting check-ins.');
        }

        return $occurrenceId;
    }

    private function statusValue(Model $model): string
    {
        $status = $model->getAttribute('status');

        return is_object($status) && method_exists($status, 'getValue')
            ? (string) $status->getValue()
            : (string) $status;
    }

    private function resolveSession(string $eventId, mixed $sessionId): ?Model
    {
        if ($sessionId === null) {
            return null;
        }

        $sessionClass = ModelResolver::sessionClass();

        /** @var Model|null $session */
        $session = $sessionClass::query()
            ->whereKey($sessionId)
            ->where('event_id', $eventId)
            ->first();

        if ($session === null) {
            throw new InvalidArgumentException('The selected session does not belong to the selected event.');
        }

        return $session;
    }

    private function resolveRegistration(string $eventId, mixed $registrationId): ?RegistrationSummary
    {
        if ($registrationId === null) {
            return null;
        }

        $registration = $this->registrations->summarize((string) $registrationId);

        if ($registration === null || $registration->eventId !== $eventId) {
            throw new InvalidArgumentException('The selected registration does not belong to the selected event.');
        }

        return $registration;
    }

    private function resolveParticipant(
        string $eventId,
        mixed $participantId,
        ?RegistrationSummary $registration,
    ): ?EventRegistrationParticipantData {
        if ($participantId === null) {
            return null;
        }

        $participant = $this->registrations->findParticipant((string) $participantId);

        if ($participant === null || $participant->eventId !== $eventId) {
            throw new InvalidArgumentException('The selected participant does not belong to the selected registration.');
        }

        if ($registration !== null && $participant->registrationId !== $registration->id) {
            throw new InvalidArgumentException('The selected participant does not belong to the selected registration.');
        }

        return $participant;
    }

    private function resolvePass(string $eventId, mixed $passId, ?RegistrationSummary $registration): ?Pass
    {
        if ($passId === null) {
            return null;
        }

        $registrationType = $this->registrations->registrationMorphClass();

        $pass = Pass::query()
            ->whereKey($passId)
            ->with(['ticketable', 'registration', 'holder'])
            ->when(
                $registration !== null,
                fn ($query) => $query
                    ->where('registration_type', $registrationType)
                    ->where('registration_id', $registration->id),
            )
            ->first();

        if (! $pass instanceof Pass || ! $this->passBelongsToEvent($pass, $eventId)) {
            throw new InvalidArgumentException('The selected pass does not belong to the selected registration.');
        }

        return $pass;
    }

    private function passBelongsToEvent(Pass $pass, string $eventId): bool
    {
        $sessionClass = ModelResolver::sessionClass();
        $occurrenceClass = ModelResolver::occurrenceClass();
        $eventClass = ModelResolver::eventClass();

        if ($pass->session_id !== null) {
            return $sessionClass::query()
                ->whereKey($pass->session_id)
                ->where('event_id', $eventId)
                ->exists();
        }

        if ($pass->occurrence_id !== null) {
            return $occurrenceClass::query()
                ->whereKey($pass->occurrence_id)
                ->where('event_id', $eventId)
                ->exists();
        }

        $ticketable = $pass->ticketable;

        if ($ticketable instanceof $eventClass) {
            return (string) $ticketable->getKey() === $eventId;
        }

        if ($ticketable instanceof $occurrenceClass || $ticketable instanceof $sessionClass) {
            return (string) $ticketable->getAttribute('event_id') === $eventId;
        }

        $registrationId = $pass->registration_id;

        if ($registrationId === null) {
            return false;
        }

        $registration = $this->registrations->summarize((string) $registrationId);

        return $registration !== null && $registration->eventId === $eventId;
    }

    private function record(Model $attendance): AttendanceRecord
    {
        return new AttendanceRecord(
            id: (string) $attendance->getKey(),
            eventId: (string) $attendance->getAttribute('event_id'),
            occurrenceId: $this->nullableString($attendance->getAttribute('event_occurrence_id')),
            sessionId: $this->nullableString($attendance->getAttribute('event_session_id')),
            registrationId: $this->nullableString($attendance->getAttribute('event_registration_id')),
            participantId: $this->nullableString($attendance->getAttribute('event_registration_participant_id')),
            attendanceType: (string) ($attendance->getAttribute('attendance_type') ?? 'registered'),
            checkInSource: $this->nullableString($attendance->getAttribute('check_in_source')),
            checkedInAt: $this->toImmutable($attendance->getAttribute('checked_in_at')),
            checkedOutAt: $this->toImmutable($attendance->getAttribute('checked_out_at')),
            cancelledAt: $this->toImmutable($attendance->getAttribute('cancelled_at')),
            verifiedByUserId: $this->nullableString($attendance->getAttribute('verified_by_user_id')),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function toImmutable(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return null;
    }
}
