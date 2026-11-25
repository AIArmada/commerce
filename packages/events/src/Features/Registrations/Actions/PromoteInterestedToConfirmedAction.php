<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Contracts\EventPassIssuanceService;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Exceptions\NotInterestedRegistrationException;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\Queries\RegistrationCapacityQuery;
use AIArmada\Events\Features\Registrations\States\RegistrationStatus\Confirmed;
use AIArmada\Events\Features\Registrations\States\RegistrationStatus\Interested;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class PromoteInterestedToConfirmedAction
{
    public function __construct(
        private readonly EventPassIssuanceService $passes,
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly LockEventRegistrationScopeAction $lockScope,
        private readonly RegistrationCapacityQuery $capacity,
    ) {}

    public function execute(string $registrationId): void
    {
        DB::transaction(function () use ($registrationId): void {
            /** @var EventRegistration $registration */
            $registration = EventRegistration::query()
                ->whereKey($registrationId)
                ->lockForUpdate()
                ->firstOrFail();

            EventWriteGuard::findOrFail((string) $registration->event_id);

            if (! $registration->status instanceof Interested) {
                throw new NotInterestedRegistrationException(
                    sprintf('Registration %s is not in Interested status.', $registrationId),
                );
            }

            $scopeRef = $this->scopeRef($registration);
            $scope = $this->scopeResolver->resolve($scopeRef['type'], $scopeRef['id']);

            $this->lockScope->handle($scope);

            $registration->refresh();

            if (! $registration->status instanceof Interested) {
                throw new NotInterestedRegistrationException(
                    sprintf('Registration %s is not in Interested status.', $registrationId),
                );
            }

            $capacityRemaining = $this->capacityRemaining($registration, $scope->capacity);
            $seatsRequired = max(1, (int) $registration->total_participants);

            if ($capacityRemaining !== null && $capacityRemaining < $seatsRequired) {
                throw new EventCapacityExceededException(
                    sprintf(
                        '%s %s is at capacity. Cannot promote registration %s.',
                        $scopeRef['label'],
                        $scopeRef['id'],
                        $registrationId,
                    ),
                );
            }

            if ($registration->event_occurrence_id !== null) {
                $occurrenceClass = ModelResolver::occurrenceClass();

                /** @var Model|null $occurrence */
                $occurrence = $occurrenceClass::query()->whereKey($registration->event_occurrence_id)->first();
                $occurrenceCapacity = $occurrence?->getAttribute('capacity');

                if ($occurrenceCapacity !== null
                    && $this->occurrenceCapacityRemaining($registration, (int) $occurrenceCapacity) < $seatsRequired) {
                    throw new EventCapacityExceededException(
                        sprintf(
                            'Occurrence %s is at capacity. Cannot promote registration %s.',
                            $registration->event_occurrence_id,
                            $registrationId,
                        ),
                    );
                }
            }

            $registration->transitionStatus(Confirmed::class);

            if ($registration->isDirty()) {
                $registration->save();
            }

            if ($scope->shouldIssuePasses) {
                $this->passes->issueForRegistration($registrationId);
            }
        });
    }

    /**
     * @return array{type: string, id: string, label: string}
     */
    private function scopeRef(EventRegistration $registration): array
    {
        if ($registration->event_session_id !== null) {
            return ['type' => 'session', 'id' => (string) $registration->event_session_id, 'label' => 'Session'];
        }

        if ($registration->event_occurrence_id !== null) {
            return ['type' => 'occurrence', 'id' => (string) $registration->event_occurrence_id, 'label' => 'Occurrence'];
        }

        return ['type' => 'event', 'id' => (string) $registration->event_id, 'label' => 'Event'];
    }

    private function capacityRemaining(EventRegistration $registration, ?int $capacity): ?int
    {
        if ($capacity === null) {
            return null;
        }

        $query = EventRegistration::query()->where('event_id', $registration->event_id);

        $this->applyNullSafeScope($query, 'event_occurrence_id', $registration->event_occurrence_id);
        $this->applyNullSafeScope($query, 'event_session_id', $registration->event_session_id);

        $reserved = (int) $query
            ->whereIn('status', config(
                'events.lifecycle.registration.capacity_blocking_statuses',
                ['pending', 'confirmed', 'refund_pending', 'checked_in'],
            ))
            ->sum('total_participants');

        return max(0, $capacity - $reserved);
    }

    private function applyNullSafeScope(Builder $query, string $column, ?string $value): void
    {
        if ($value === null) {
            $query->whereNull($column);
        } else {
            $query->where($column, $value);
        }
    }

    private function occurrenceCapacityRemaining(EventRegistration $registration, int $capacity): int
    {
        $reserved = $this->capacity->reserved(
            (string) $registration->event_id,
            $registration->event_occurrence_id !== null ? (string) $registration->event_occurrence_id : null,
            null,
        );

        return max(0, $capacity - $reserved);
    }
}
