<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\Events\Contracts\EventRegistrationEligibility;
use AIArmada\Events\Exceptions\EventRegistrationNotAvailableException;
use AIArmada\Events\Support\EventRegistrationScope;
use AIArmada\Events\Support\Policy\LifecyclePolicy;

final class DefaultEventRegistrationEligibility implements EventRegistrationEligibility
{
    public function __construct(
        private readonly LifecyclePolicy $lifecyclePolicy,
    ) {}

    public function ensureEligible(EventRegistrationScope $scope): void
    {
        if (! $this->lifecyclePolicy->canAcceptRegistrationsForEvent($scope->event)) {
            throw new EventRegistrationNotAvailableException(sprintf(
                'Registration is not available for event %s while it is %s.',
                $scope->event->getKey(),
                $scope->event->status->getValue(),
            ));
        }

        if ($scope->occurrence !== null && ! $this->lifecyclePolicy->canAcceptRegistrations($scope->occurrence)) {
            throw new EventRegistrationNotAvailableException(sprintf(
                'Registration is not available for occurrence %s while it is %s.',
                $scope->occurrence->getKey(),
                $scope->occurrence->status->getValue(),
            ));
        }

        if ($scope->session !== null && ! $this->lifecyclePolicy->canAcceptRegistrationsForSession($scope->session)) {
            throw new EventRegistrationNotAvailableException(sprintf(
                'Registration is not available for session %s while it is %s.',
                $scope->session->getKey(),
                $scope->session->status->getValue(),
            ));
        }
    }
}
