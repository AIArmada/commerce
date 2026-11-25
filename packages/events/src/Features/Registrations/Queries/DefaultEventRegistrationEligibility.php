<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Queries;

use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Events\Exceptions\EventRegistrationNotAvailableException;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Platform\LifecyclePolicy;

final class DefaultEventRegistrationEligibility implements EventRegistrationEligibility
{
    public function __construct(
        private readonly LifecyclePolicy $lifecyclePolicy,
        private readonly CatalogApi $catalog,
    ) {}

    public function ensureEligible(EventRegistrationScopeData $scope): void
    {
        $event = $this->catalog->describeScope('event', $scope->eventId);
        $eventStatus = $event?->status ?? '';

        if (! $this->lifecyclePolicy->canAcceptRegistrationsForEvent($eventStatus)) {
            throw new EventRegistrationNotAvailableException(sprintf(
                'Registration is not available for event %s while it is %s.',
                $scope->eventId,
                $eventStatus,
            ));
        }

        if ($scope->occurrenceId !== null) {
            $occurrence = $this->catalog->describeScope('occurrence', $scope->occurrenceId);
            $occurrenceStatus = $occurrence?->status ?? '';

            if (! $this->lifecyclePolicy->canAcceptRegistrations($occurrenceStatus)) {
                throw new EventRegistrationNotAvailableException(sprintf(
                    'Registration is not available for occurrence %s while it is %s.',
                    $scope->occurrenceId,
                    $occurrenceStatus,
                ));
            }
        }

        if ($scope->sessionId !== null) {
            $session = $this->catalog->describeScope('session', $scope->sessionId);
            $sessionStatus = $session?->status ?? '';

            if (! $this->lifecyclePolicy->canAcceptRegistrationsForSession($sessionStatus)) {
                throw new EventRegistrationNotAvailableException(sprintf(
                    'Registration is not available for session %s while it is %s.',
                    $scope->sessionId,
                    $sessionStatus,
                ));
            }
        }
    }
}
