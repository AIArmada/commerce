<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Contracts\EventPassIssuanceService;
use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Exceptions\NotInterestedRegistrationException;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\Queries\RegistrationCapacityQuery;
use AIArmada\Events\Features\Registrations\States\RegistrationStatus\Confirmed;
use AIArmada\Events\Features\Registrations\States\RegistrationStatus\Interested;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class PromoteInterestedToConfirmedAction
{
    public function __construct(
        private readonly EventPassIssuanceService $passes,
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly EventRegistrationEligibility $eligibility,
        private readonly LockEventRegistrationScopeAction $lockScope,
        private readonly RegistrationCapacityQuery $capacity,
    ) {}

    public function execute(string $registrationId): void
    {
        DB::transaction(function () use ($registrationId): void {
            /** @var EventRegistration $registration */
            $registration = EventRegistration::query()
                ->whereKey($registrationId)
                ->firstOrFail();

            EventWriteGuard::findOrFail((string) $registration->event_id);

            if (! $registration->status instanceof Interested) {
                throw new NotInterestedRegistrationException(
                    sprintf('Registration %s is not in Interested status.', $registrationId),
                );
            }

            $scopeRef = $this->scopeRef($registration);
            $scope = $this->scopeResolver->resolve($scopeRef['type'], $scopeRef['id']);
            $this->eligibility->ensureEligible($scope);

            $this->lockScope->handle($scope);

            /** @var EventRegistration $registration */
            $registration = EventRegistration::query()
                ->whereKey($registrationId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $registration->status instanceof Interested) {
                throw new NotInterestedRegistrationException(
                    sprintf('Registration %s is not in Interested status.', $registrationId),
                );
            }

            $scope = $this->scopeResolver->resolve($scopeRef['type'], $scopeRef['id']);
            $this->eligibility->ensureEligible($scope);

            $seatsRequired = max(1, (int) $registration->total_participants);

            if ($scope->occurrenceId !== null) {
                $occurrenceClass = ModelResolver::occurrenceClass();

                /** @var Model|null $occurrence */
                $occurrence = $occurrenceClass::query()->whereKey($scope->occurrenceId)->first();
                $occurrenceCapacity = $occurrence?->getAttribute('capacity');

                if ($occurrenceCapacity !== null) {
                    $occurrenceRemaining = $this->capacity->remaining(
                        eventId: $scope->eventId,
                        occurrenceId: $scope->occurrenceId,
                        sessionId: null,
                        capacity: (int) $occurrenceCapacity,
                    );

                    if ($occurrenceRemaining !== null && $occurrenceRemaining < $seatsRequired) {
                        throw new EventCapacityExceededException(
                            sprintf(
                                'Occurrence %s is at capacity. Cannot promote registration %s.',
                                $scope->occurrenceId,
                                $registrationId,
                            ),
                        );
                    }
                }
            }

            $capacityRemaining = $this->capacity->remaining(
                eventId: $scope->eventId,
                occurrenceId: $scope->occurrenceId,
                sessionId: $scope->sessionId,
                capacity: $scope->capacity,
            );

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
}
