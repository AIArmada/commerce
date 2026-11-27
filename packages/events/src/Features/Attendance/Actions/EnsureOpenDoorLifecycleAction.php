<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Attendance\Actions;

use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Events\Exceptions\EventRegistrationNotAvailableException;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Platform\LifecyclePolicy;

final class EnsureOpenDoorLifecycleAction
{
    public function __construct(
        private readonly CatalogApi $catalog,
        private readonly LifecyclePolicy $lifecycle,
    ) {}

    public function handle(EventRegistrationScopeData $scope): void
    {
        $event = $this->catalog->describeScope('event', $scope->eventId);

        if ($event === null || ! $this->lifecycle->canAcceptRegistrationsForEvent($event->status ?? '')) {
            throw new EventRegistrationNotAvailableException(sprintf(
                'Event %s is not accepting attendance.',
                $scope->eventId,
            ));
        }

        if ($scope->occurrenceId !== null) {
            $occurrence = $this->catalog->describeScope('occurrence', $scope->occurrenceId);

            if ($occurrence === null || ! $this->lifecycle->canWalkIn($occurrence->status ?? '')) {
                throw new EventRegistrationNotAvailableException(sprintf(
                    'Occurrence %s is not accepting attendance.',
                    $scope->occurrenceId,
                ));
            }
        }

        if ($scope->sessionId !== null) {
            $session = $this->catalog->describeScope('session', $scope->sessionId);

            if ($session === null || ! $this->lifecycle->canAcceptRegistrationsForSession($session->status ?? '')) {
                throw new EventRegistrationNotAvailableException(sprintf(
                    'Session %s is not accepting attendance.',
                    $scope->sessionId,
                ));
            }
        }
    }
}
