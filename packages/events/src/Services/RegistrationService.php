<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Events\RegistrationCancelled;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Events\RegistrationCreated;
use AIArmada\Events\Events\RegistrationMarkedNoShow;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Orders\Models\OrderItem;
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
                'checked_in_at' => now(),
                'metadata' => $metadata,
            ]);

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
                'cancelled_at' => now(),
                'metadata' => $metadata,
            ]);

            event(new RegistrationCancelled($registration->refresh(), $reason));

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

        $status = $this->resolveStatus($links);

        $registration = Registration::create([
            'occurrence_id' => $occurrence->id,
            'order_id' => $this->resolveModelKey($links['order'] ?? $links['order_id'] ?? null),
            'order_item_id' => $this->resolveModelKey($links['order_item'] ?? $links['order_item_id'] ?? null),
            'purchaser_customer_id' => $this->resolveModelKey($links['purchaser_customer'] ?? $links['purchaser_customer_id'] ?? null),
            'participant_customer_id' => $this->resolveModelKey($links['participant_customer'] ?? $links['participant_customer_id'] ?? null),
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
     * @param  array<string, mixed>  $links
     */
    private function resolveStatus(array $links): RegistrationStatus
    {
        $status = $links['status'] ?? null;

        if ($status instanceof RegistrationStatus) {
            return $status;
        }

        if (is_string($status) && RegistrationStatus::tryFrom($status) instanceof RegistrationStatus) {
            return RegistrationStatus::from($status);
        }

        if (($links['order_item'] ?? $links['order_item_id'] ?? null) !== null) {
            return RegistrationStatus::Confirmed;
        }

        return RegistrationStatus::Pending;
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

    private function ensureOccurrenceCanAcceptRegistrations(Occurrence $occurrence, int $requestedSeats): void
    {
        if (! $occurrence->acceptsRegistrations()) {
            throw new InvalidArgumentException('This event date is not accepting registrations.');
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
        $now = now();

        if ($occurrence->ends_at !== null) {
            return $occurrence->ends_at->lte($now);
        }

        if ($occurrence->check_in_closes_at !== null) {
            return $occurrence->check_in_closes_at->lte($now);
        }

        return false;
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
