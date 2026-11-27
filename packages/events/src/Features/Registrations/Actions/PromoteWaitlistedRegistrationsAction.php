<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Contracts\RegistrationServiceInterface;
use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\Queries\RegistrationCapacityQuery;
use AIArmada\Events\Features\Registrations\States\RegistrationStatus\Waitlisted;
use AIArmada\Events\Platform\EventWriteGuard;
use Illuminate\Support\Facades\DB;

final class PromoteWaitlistedRegistrationsAction
{
    public function __construct(
        private readonly RegistrationServiceInterface $registrationService,
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly EventRegistrationEligibility $eligibility,
        private readonly LockEventRegistrationScopeAction $lockScope,
        private readonly RegistrationCapacityQuery $capacity,
    ) {}

    public function handle(string $registrationId): void
    {
        DB::transaction(function () use ($registrationId): void {
            /** @var EventRegistration $registration */
            $registration = EventRegistration::query()
                ->whereKey($registrationId)
                ->firstOrFail();

            if (! $registration->status instanceof Waitlisted) {
                return;
            }

            EventWriteGuard::findOrFail((string) $registration->event_id);

            $scope = $this->resolveScope($registration);
            $this->lockScope->handle($scope);
            $this->promoteLocked($registrationId, $scope);
        });
    }

    /**
     * Promote every waitlisted registration in request order.
     *
     * Promotion stops when the next reservation does not fit the remaining
     * capacity so later requests cannot overtake earlier ones.
     */
    public function handleScope(string $scopeType, string $scopeId): int
    {
        return DB::transaction(function () use ($scopeType, $scopeId): int {
            $scope = $this->scopeResolver->resolve($scopeType, $scopeId);
            $this->eligibility->ensureEligible($scope);
            $this->lockScope->handle($scope);

            $query = EventRegistration::query()
                ->where('status', 'waitlisted')
                ->orderBy('waitlisted_at')
                ->orderBy('id');

            $query->where('event_id', $scope->eventId);

            if ($scope->occurrenceId !== null) {
                $query->where('event_occurrence_id', $scope->occurrenceId);
            } else {
                $query->whereNull('event_occurrence_id');
            }

            if ($scope->sessionId !== null) {
                $query->where('event_session_id', $scope->sessionId);
            } else {
                $query->whereNull('event_session_id');
            }

            $promoted = 0;

            foreach ($query->pluck('id') as $registrationId) {
                try {
                    if ($this->promoteLocked((string) $registrationId, $scope)) {
                        $promoted++;
                    }
                } catch (EventCapacityExceededException) {
                    break;
                }
            }

            return $promoted;
        });
    }

    private function promoteLocked(string $registrationId, EventRegistrationScopeData $scope): bool
    {
        /** @var EventRegistration $registration */
        $registration = EventRegistration::query()
            ->whereKey($registrationId)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $registration->status instanceof Waitlisted) {
            return false;
        }

        $this->eligibility->ensureEligible($scope);

        $capacityRemaining = $this->capacity->remaining(
            eventId: $scope->eventId,
            occurrenceId: $scope->occurrenceId,
            sessionId: $scope->sessionId,
            capacity: $scope->capacity,
        );
        $seatsRequired = max(1, (int) $registration->total_participants);

        if ($capacityRemaining !== null && $capacityRemaining < $seatsRequired) {
            throw new EventCapacityExceededException(sprintf(
                'The event scope does not have enough capacity to promote registration %s.',
                $registrationId,
            ));
        }

        $this->registrationService->approve($registrationId);

        return true;
    }

    private function resolveScope(EventRegistration $registration): EventRegistrationScopeData
    {
        if ($registration->event_session_id !== null) {
            return $this->scopeResolver->resolve('session', (string) $registration->event_session_id);
        }

        if ($registration->event_occurrence_id !== null) {
            return $this->scopeResolver->resolve('occurrence', (string) $registration->event_occurrence_id);
        }

        return $this->scopeResolver->resolve('event', (string) $registration->event_id);
    }
}
