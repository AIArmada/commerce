<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Enums\RegistrationAttendanceSource;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Events\RegistrationApproved;
use AIArmada\Events\Events\RegistrationCancelled;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Events\RegistrationCreated;
use AIArmada\Events\Events\RegistrationMarkedNoShow;
use AIArmada\Events\Events\RegistrationRefunded;
use AIArmada\Events\Events\RegistrationRejected;
use AIArmada\Events\Events\WalkInRecorded;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttendance;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class RegistrationService
{
    /**
     * @param  array<string, mixed>  $participant
     * @param  array<string, mixed>  $links
     */
    public function createForOccurrence(Occurrence $occurrence, array $participant, array $links = []): Registration
    {
        return $this->withRecordOwnerContext($occurrence, function () use ($occurrence, $participant, $links): Registration {
            return DB::transaction(function () use ($occurrence, $participant, $links): Registration {
                $lockedOccurrence = $this->lockOccurrence($occurrence);

                $this->ensureOccurrenceCanAcceptRegistrations($lockedOccurrence, 1);

                return $this->createRegistration($lockedOccurrence, $participant, $links);
            });
        });
    }

    /**
     * @param  array<string, mixed>  $participant
     * @param  array<string, mixed>  $links
     */
    public function recordWalkInForOccurrence(Occurrence $occurrence, array $participant = [], array $links = []): Registration
    {
        return $this->withRecordOwnerContext($occurrence, function () use ($occurrence, $participant, $links): Registration {
            return DB::transaction(function () use ($occurrence, $participant, $links): Registration {
                $lockedOccurrence = $this->lockOccurrence($occurrence);

                $this->ensureOccurrenceCanAcceptWalkIns($lockedOccurrence, 1);

                return $this->createWalkInAttendance($lockedOccurrence, $participant, $links);
            });
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @return Collection<int, Registration>
     */
    public function createBatchForOrderItem(
        Occurrence $occurrence,
        OrderItem $orderItem,
        array $participants,
        ?Customer $purchaser = null,
    ): Collection {
        $expectedCount = max(1, (int) $orderItem->quantity);

        if (count($participants) !== $expectedCount) {
            throw new InvalidArgumentException(sprintf(
                'Expected %d participant payloads for order item %s, received %d.',
                $expectedCount,
                (string) $orderItem->id,
                count($participants),
            ));
        }

        return $this->withRecordOwnerContext($occurrence, function () use ($occurrence, $expectedCount, $orderItem, $participants, $purchaser): Collection {
            return DB::transaction(function () use ($occurrence, $expectedCount, $orderItem, $participants, $purchaser): Collection {
                $lockedOccurrence = $this->lockOccurrence($occurrence);

                $this->ensureOccurrenceCanAcceptRegistrations($lockedOccurrence, $expectedCount);

                return new Collection(array_map(function (array $participant) use ($lockedOccurrence, $orderItem, $purchaser): Registration {
                    return $this->createRegistration($lockedOccurrence, $participant, [
                        'order_id' => $orderItem->order_id,
                        'order_item_id' => $orderItem->id,
                        'purchaser_customer_id' => $purchaser?->id,
                        'status' => RegistrationStatus::Confirmed,
                    ]);
                }, $participants));
            });
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function checkIn(Registration $registration, array $context = []): Registration
    {
        return $this->withRecordOwnerContext($registration, function () use ($registration, $context): Registration {
            if (! $registration->status->canCheckIn()) {
                throw new InvalidArgumentException(sprintf(
                    'Registration %s cannot be checked in from status %s.',
                    $registration->id,
                    $registration->status->value,
                ));
            }

            $occurrence = $this->occurrenceForRegistration($registration);

            if (! $occurrence->acceptsCheckIn()) {
                throw new InvalidArgumentException('This event date is not currently open for check-in.');
            }

            $metadata = array_merge(Arr::wrap($registration->metadata), [
                'check_in_context' => $context,
            ]);

            $registration->update([
                'status' => RegistrationStatus::CheckedIn,
                'checked_in_at' => now('UTC'),
                'metadata' => $metadata,
            ]);

            EventAttendance::updateOrCreate(
                [
                    'registration_id' => $registration->id,
                    'source' => RegistrationAttendanceSource::Registration->value,
                ],
                [
                    'event_id' => $occurrence->event_id,
                    'occurrence_id' => $occurrence->id,
                    'attendee_type' => $registration->attendee_type,
                    'attendee_id' => $registration->attendee_id,
                    'recorded_by_type' => OwnerContext::resolve()?->getMorphClass(),
                    'recorded_by_id' => OwnerContext::resolve()?->getKey(),
                    'status' => 'present',
                    'checked_in_at' => $registration->checked_in_at,
                    'metadata' => $metadata,
                ],
            );

            event(new RegistrationCheckedIn($registration->refresh(), $context));

            return $registration->refresh();
        });
    }

    public function cancel(Registration $registration, ?string $reason = null): Registration
    {
        return $this->withRecordOwnerContext($registration, function () use ($registration, $reason): Registration {
            if ($registration->status === RegistrationStatus::Cancelled) {
                return $registration;
            }

            $metadata = array_merge(Arr::wrap($registration->metadata), array_filter([
                'cancellation_reason' => $reason,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            $registration->update([
                'status' => RegistrationStatus::Cancelled,
                'cancelled_at' => now('UTC'),
                'metadata' => $metadata,
            ]);

            event(new RegistrationCancelled($registration->refresh(), $reason));

            return $registration->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function approve(Registration $registration, ?Model $actor = null, array $context = []): Registration
    {
        return $this->withRecordOwnerContext($registration, function () use ($registration, $actor, $context): Registration {
            return DB::transaction(function () use ($registration, $actor, $context): Registration {
                $lockedRegistration = $this->lockRegistration($registration);

                if ($lockedRegistration->status === RegistrationStatus::Confirmed) {
                    return $lockedRegistration;
                }

                if (! in_array($lockedRegistration->status, [RegistrationStatus::Pending, RegistrationStatus::Waitlisted], true)) {
                    throw new InvalidArgumentException(sprintf(
                        'Registration %s cannot be approved from status %s.',
                        $lockedRegistration->id,
                        $lockedRegistration->status->value,
                    ));
                }

                $lockedOccurrence = $this->lockOccurrence($this->occurrenceForRegistration($lockedRegistration));
                $this->ensureOccurrenceHasCapacity($lockedOccurrence, 1);

                $metadata = $this->buildApprovalMetadata($lockedRegistration, $actor, $context, 'approve');

                $lockedRegistration->update([
                    'status' => RegistrationStatus::Confirmed,
                    'metadata' => $metadata,
                ]);

                event(new RegistrationApproved($lockedRegistration->refresh(), $actor, $context));

                return $lockedRegistration->refresh();
            });
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function reject(Registration $registration, ?Model $actor = null, ?string $reason = null, array $context = []): Registration
    {
        return $this->withRecordOwnerContext($registration, function () use ($registration, $actor, $reason, $context): Registration {
            if ($registration->status === RegistrationStatus::Cancelled) {
                return $registration;
            }

            if (! in_array($registration->status, [RegistrationStatus::Pending, RegistrationStatus::Waitlisted], true)) {
                throw new InvalidArgumentException(sprintf(
                    'Registration %s cannot be rejected from status %s.',
                    $registration->id,
                    $registration->status->value,
                ));
            }

            $metadata = $this->buildApprovalMetadata($registration, $actor, $context, 'reject', $reason);

            $registration->update([
                'status' => RegistrationStatus::Cancelled,
                'cancelled_at' => now('UTC'),
                'metadata' => $metadata,
            ]);

            event(new RegistrationRejected($registration->refresh(), $reason, $actor, $context));

            return $registration->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function refund(Registration $registration, ?string $reason = null, array $context = []): Registration
    {
        return $this->withRecordOwnerContext($registration, function () use ($registration, $reason, $context): Registration {
            if ($registration->status === RegistrationStatus::Refunded) {
                return $registration;
            }

            $metadata = array_merge(Arr::wrap($registration->metadata), array_filter([
                'refund_reason' => $reason,
                'refund_context' => $context,
            ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []));

            $registration->update([
                'status' => RegistrationStatus::Refunded,
                'metadata' => $metadata,
            ]);

            event(new RegistrationRefunded($registration->refresh(), $reason, $context));

            return $registration->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function markNoShow(Registration $registration, array $context = []): Registration
    {
        return $this->withRecordOwnerContext($registration, function () use ($registration, $context): Registration {
            if ($registration->status === RegistrationStatus::NoShow) {
                return $registration;
            }

            if ($registration->status !== RegistrationStatus::Confirmed) {
                throw new InvalidArgumentException(sprintf(
                    'Registration %s cannot be marked as no-show from status %s.',
                    $registration->id,
                    $registration->status->value,
                ));
            }

            $occurrence = $this->occurrenceForRegistration($registration);

            if (! $this->occurrenceHasEnded($occurrence)) {
                throw new InvalidArgumentException('This event date has not ended yet.');
            }

            $metadata = array_merge(Arr::wrap($registration->metadata), [
                'no_show_context' => $context,
            ]);

            $registration->update([
                'status' => RegistrationStatus::NoShow,
                'metadata' => $metadata,
            ]);

            event(new RegistrationMarkedNoShow($registration->refresh(), $context));

            return $registration->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $participant
     * @param  array<string, mixed>  $links
     */
    private function createRegistration(Occurrence $occurrence, array $participant, array $links = []): Registration
    {
        [$firstName, $lastName] = $this->resolveParticipantName($participant);
        $email = $this->requireString($participant, 'email');
        $phone = $this->optionalString($participant, 'phone');
        $company = $this->optionalString($participant, 'company');
        $metadata = array_merge(
            Arr::wrap($links['metadata'] ?? []),
            Arr::wrap($participant['metadata'] ?? []),
        );

        [$attendeeType, $attendeeId] = $this->resolveAttendeeIdentity($participant, $links);
        $this->guardAgainstDuplicateRegistration(
            $occurrence,
            $attendeeType,
            $attendeeId,
            $email,
            $phone,
        );

        $status = $this->resolveStatus($occurrence, $links);

        if ($occurrence->isWaitlistEnabled()) {
            try {
                $this->ensureOccurrenceHasCapacity($occurrence, 1);
            } catch (InvalidArgumentException) {
                $status = RegistrationStatus::Waitlisted;
            }
        }

        $registration = Registration::create([
            'code' => Registration::generateUniqueCode(),
            'occurrence_id' => $occurrence->id,
            'order_id' => $this->resolveModelKey($links['order'] ?? $links['order_id'] ?? null),
            'order_item_id' => $this->resolveModelKey($links['order_item'] ?? $links['order_item_id'] ?? null),
            'purchaser_customer_id' => $this->resolveModelKey($links['purchaser_customer'] ?? $links['purchaser_customer_id'] ?? null),
            'participant_customer_id' => $this->resolveModelKey($links['participant_customer'] ?? $links['participant_customer_id'] ?? null),
            'attendance_source' => RegistrationAttendanceSource::Registration,
            'attendee_type' => $attendeeType,
            'attendee_id' => $attendeeId,
            'status' => $status,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);

        event(new RegistrationCreated($registration));

        return $registration;
    }

    /**
     * @param  array<string, mixed>  $participant
     * @param  array<string, mixed>  $links
     */
    private function createWalkInAttendance(Occurrence $occurrence, array $participant, array $links = []): Registration
    {
        [$firstName, $lastName] = $this->resolveWalkInParticipantName($participant);
        $metadata = array_merge(
            Arr::wrap($links['metadata'] ?? []),
            Arr::wrap($participant['metadata'] ?? []),
        );
        [$attendeeType, $attendeeId] = $this->resolveAttendeeIdentity($participant, $links);

        $registration = Registration::create([
            'code' => Registration::generateUniqueCode(),
            'occurrence_id' => $occurrence->id,
            'attendance_source' => RegistrationAttendanceSource::WalkIn,
            'attendee_type' => $attendeeType,
            'attendee_id' => $attendeeId,
            'status' => RegistrationStatus::CheckedIn,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $this->optionalString($participant, 'email'),
            'phone' => $this->optionalString($participant, 'phone'),
            'company' => $this->optionalString($participant, 'company'),
            'checked_in_at' => now('UTC'),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);

        EventAttendance::updateOrCreate(
            [
                'registration_id' => $registration->id,
                'source' => RegistrationAttendanceSource::WalkIn->value,
            ],
            [
                'event_id' => $occurrence->event_id,
                'occurrence_id' => $occurrence->id,
                'attendee_type' => $registration->attendee_type,
                'attendee_id' => $registration->attendee_id,
                'recorded_by_type' => OwnerContext::resolve()?->getMorphClass(),
                'recorded_by_id' => OwnerContext::resolve()?->getKey(),
                'status' => 'present',
                'checked_in_at' => $registration->checked_in_at,
                'metadata' => $registration->metadata,
            ],
        );

        event(new RegistrationCreated($registration));
        event(new WalkInRecorded($registration, [
            'source' => 'walk_in',
        ]));

        return $registration;
    }

    /**
     * @param  array<string, mixed>  $participant
     * @return array{0: string, 1: string}
     */
    private function resolveParticipantName(array $participant): array
    {
        $firstName = $this->optionalString($participant, 'first_name');
        $lastName = $this->optionalString($participant, 'last_name');

        if ($firstName !== null) {
            return [$firstName, $lastName ?? ''];
        }

        $name = $this->requireString($participant, 'name');
        $segments = preg_split('/\s+/', $name, 2) ?: [];

        return [
            $segments[0] ?? $name,
            $segments[1] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $participant
     * @return array{0: string, 1: string}
     */
    private function resolveWalkInParticipantName(array $participant): array
    {
        $firstName = $this->optionalString($participant, 'first_name');
        $lastName = $this->optionalString($participant, 'last_name');

        if ($firstName !== null) {
            return [$firstName, $lastName ?? ''];
        }

        $name = $this->optionalString($participant, 'name');

        if ($name === null) {
            return ['Walk-in', 'Attendee'];
        }

        $segments = preg_split('/\s+/', $name, 2) ?: [];

        return [
            $segments[0] ?? $name,
            $segments[1] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $links
     */
    private function resolveStatus(Occurrence $occurrence, array $links): RegistrationStatus
    {
        $status = $links['status'] ?? null;

        if ($status instanceof RegistrationStatus) {
            $resolved = $status;
        } elseif (is_string($status) && RegistrationStatus::tryFrom($status) instanceof RegistrationStatus) {
            $resolved = RegistrationStatus::from($status);
        } elseif (($links['order_item'] ?? $links['order_item_id'] ?? null) !== null) {
            $resolved = RegistrationStatus::Confirmed;
        } else {
            $resolved = RegistrationStatus::Pending;
        }

        if ($occurrence->requiresApproval() && $resolved === RegistrationStatus::Confirmed) {
            return RegistrationStatus::Pending;
        }

        return $resolved;
    }

    private function lockOccurrence(Occurrence $occurrence): Occurrence
    {
        $lockedOccurrence = Occurrence::query()
            ->whereKey($occurrence->getKey())
            ->lockForUpdate()
            ->first();

        if ($lockedOccurrence instanceof Occurrence) {
            return $lockedOccurrence;
        }

        throw new InvalidArgumentException(sprintf(
            'Occurrence %s could not be found.',
            (string) $occurrence->getKey(),
        ));
    }

    private function lockRegistration(Registration $registration): Registration
    {
        $lockedRegistration = Registration::query()
            ->whereKey($registration->getKey())
            ->lockForUpdate()
            ->first();

        if ($lockedRegistration instanceof Registration) {
            return $lockedRegistration;
        }

        throw new InvalidArgumentException(sprintf(
            'Registration %s could not be found.',
            (string) $registration->getKey(),
        ));
    }

    private function ensureOccurrenceCanAcceptRegistrations(Occurrence $occurrence, int $requestedSeats): void
    {
        $event = $occurrence->event;

        if ($event instanceof Event && ! $event->isEngageable()) {
            throw new InvalidArgumentException(sprintf(
                'Registrations are not accepted while the event is [%s].',
                $event->status->value,
            ));
        }

        if ($event instanceof Event && (bool) $event->registration_required === false) {
            throw new InvalidArgumentException('Registrations are not required for this event.');
        }

        if (! $occurrence->acceptsRegistrations()) {
            throw new InvalidArgumentException('This event date is not accepting registrations.');
        }

        try {
            $this->ensureOccurrenceHasCapacity($occurrence, $requestedSeats);
        } catch (InvalidArgumentException $exception) {
            if (! $occurrence->isWaitlistEnabled()) {
                throw $exception;
            }
        }
    }

    private function ensureOccurrenceCanAcceptWalkIns(Occurrence $occurrence, int $requestedSeats): void
    {
        $event = $occurrence->event;

        if ($event instanceof Event && ! $event->isEngageable()) {
            throw new InvalidArgumentException(sprintf(
                'Walk-ins are not accepted while the event is [%s].',
                $event->status->value,
            ));
        }

        if (! $occurrence->acceptsWalkIns()) {
            throw new InvalidArgumentException('This event date is not accepting walk-ins.');
        }

        $this->ensureOccurrenceHasCapacity($occurrence, $requestedSeats);
    }

    private function ensureOccurrenceHasCapacity(Occurrence $occurrence, int $requestedSeats): void
    {
        $capacity = is_int($occurrence->capacity) ? $occurrence->capacity : null;

        if ($capacity === null) {
            return;
        }

        $reservedSeats = Registration::query()
            ->where('occurrence_id', $occurrence->id)
            ->whereIn('status', RegistrationStatus::capacityBlockingValues())
            ->count();

        if (($reservedSeats + $requestedSeats) <= $capacity) {
            return;
        }

        $remainingSeats = max(0, $capacity - $reservedSeats);

        if ($remainingSeats === 0) {
            throw new InvalidArgumentException('This event date is sold out.');
        }

        throw new InvalidArgumentException(sprintf(
            'Only %d seat(s) remain for this event date.',
            $remainingSeats,
        ));
    }

    private function guardAgainstDuplicateRegistration(
        Occurrence $occurrence,
        ?string $attendeeType,
        ?string $attendeeId,
        ?string $email,
        ?string $phone,
    ): void {
        $duplicateStrategy = mb_strtolower($occurrence->duplicateStrategy());

        if ($duplicateStrategy === 'none') {
            return;
        }

        $query = Registration::query()
            ->whereNotIn('status', [
                RegistrationStatus::Cancelled->value,
                RegistrationStatus::Refunded->value,
            ]);

        if ($duplicateStrategy === 'per_event') {
            $query->whereHas('occurrence', function (Builder $query) use ($occurrence): void {
                $query->where('event_id', $occurrence->event_id);
            });
        } elseif ($duplicateStrategy === 'per_series') {
            $seriesId = $occurrence->event()->value('event_series_id');

            if ($seriesId === null) {
                $query->where('occurrence_id', $occurrence->id);
            } else {
                $query->whereHas('occurrence.event', function (Builder $query) use ($seriesId): void {
                    $query->where('event_series_id', $seriesId);
                });
            }
        } else {
            $query->where('occurrence_id', $occurrence->id);
        }

        if ($attendeeType !== null && $attendeeId !== null) {
            $query->where('attendee_type', $attendeeType)
                ->where('attendee_id', $attendeeId);
        } elseif ($email !== null || $phone !== null) {
            $query->where(function (Builder $query) use ($email, $phone): void {
                if ($email !== null) {
                    $query->where('email', $email);
                }

                if ($phone !== null) {
                    if ($email !== null) {
                        $query->orWhere('phone', $phone);

                        return;
                    }

                    $query->where('phone', $phone);
                }
            });
        } else {
            return;
        }

        if (! $query->exists()) {
            return;
        }

        throw new InvalidArgumentException('A registration already exists for this attendee on this occurrence.');
    }

    private function resolveModelKey(mixed $value): ?string
    {
        if ($value instanceof Model) {
            return (string) $value->getKey();
        }

        if (is_scalar($value)) {
            $resolved = mb_trim((string) $value);

            return $resolved !== '' ? $resolved : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $participant
     * @param  array<string, mixed>  $links
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveAttendeeIdentity(array $participant, array $links): array
    {
        $attendee = $links['attendee'] ?? $participant['attendee'] ?? null;

        if ($attendee instanceof Model) {
            return [$attendee->getMorphClass(), (string) $attendee->getKey()];
        }

        $attendeeType = $this->optionalString($links, 'attendee_type')
            ?? $this->optionalString($participant, 'attendee_type');
        $attendeeId = $this->resolveModelKey($links['attendee_id'] ?? $participant['attendee_id'] ?? null);

        if ($attendeeType === null && $attendeeId === null) {
            return [null, null];
        }

        if ($attendeeType !== null && $attendeeId !== null) {
            return [$attendeeType, $attendeeId];
        }

        throw new InvalidArgumentException('Both [attendee_type] and [attendee_id] are required when providing attendee identity.');
    }

    private function occurrenceForRegistration(Registration $registration): Occurrence
    {
        $occurrence = $registration->getRelationValue('occurrence');

        if ($occurrence instanceof Occurrence) {
            return $occurrence;
        }

        $occurrence = $registration->occurrence()->first();

        if ($occurrence instanceof Occurrence) {
            return $occurrence;
        }

        throw new InvalidArgumentException(sprintf(
            'Occurrence for registration %s could not be found.',
            (string) $registration->getKey(),
        ));
    }

    private function occurrenceHasEnded(Occurrence $occurrence): bool
    {
        $now = now('UTC');

        if ($occurrence->ends_at !== null) {
            return $occurrence->ends_at->lte($now);
        }

        if ($occurrence->check_in_closes_at !== null) {
            return $occurrence->check_in_closes_at->lte($now);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildApprovalMetadata(Registration $registration, ?Model $actor, array $context, string $transition, ?string $reason = null): array
    {
        $metadata = Arr::wrap($registration->metadata);
        $metadata['approval_transition'] = $transition;

        if ($context !== []) {
            $metadata['approval_context'] = $context;
        }

        if ($reason !== null && mb_trim($reason) !== '') {
            $metadata['approval_rejection_reason'] = $reason;
            $metadata['cancellation_reason'] = $reason;
        }

        if ($actor instanceof Model) {
            $metadata['approval_actor_type'] = $actor->getMorphClass();
            $metadata['approval_actor_id'] = (string) $actor->getKey();
        }

        return $metadata;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withRecordOwnerContext(Model $record, callable $callback): mixed
    {
        if (! (bool) config('events.features.owner.enabled', true)) {
            return $callback();
        }

        $owner = $this->ownerFromModel($record);

        if ($owner instanceof Model) {
            return OwnerContext::withOwner($owner, $callback);
        }

        if (! OwnerContext::isExplicitGlobal()) {
            throw new RuntimeException(sprintf(
                'Explicit global owner context is required to operate on global %s records. Use OwnerContext::withOwner(null, ...).',
                $record::class,
            ));
        }

        return OwnerContext::withOwner(null, $callback);
    }

    private function ownerFromModel(Model $model): ?Model
    {
        $ownerType = $model->getAttribute('owner_type');
        $ownerId = $model->getAttribute('owner_id');

        return OwnerContext::fromTypeAndId(
            is_string($ownerType) ? $ownerType : null,
            is_scalar($ownerId) ? (string) $ownerId : null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requireString(array $payload, string $key): string
    {
        $value = $this->optionalString($payload, $key);

        if ($value === null) {
            throw new InvalidArgumentException(sprintf('The [%s] field is required.', $key));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $resolved = mb_trim($value);

        return $resolved !== '' ? $resolved : null;
    }
}
