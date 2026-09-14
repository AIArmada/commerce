<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Exceptions\EventCapacityExceededException;
use AIArmada\Events\Exceptions\NotInterestedRegistrationException;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\States\RegistrationStatus\Confirmed;
use AIArmada\Events\States\RegistrationStatus\Interested;
use AIArmada\Events\Support\EventWriteGuard;
use Illuminate\Support\Facades\DB;

final class PromoteInterestedToConfirmedAction
{
    public function __construct(
        private readonly IssueEventRegistrationPassesAction $issuePasses,
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly LockEventRegistrationScopeAction $lockScope,
    ) {}

    public function execute(EventRegistration $registration): EventRegistration
    {
        EventWriteGuard::findOrFail($registration->event_id);

        if (! $registration->status instanceof Interested) {
            throw new NotInterestedRegistrationException(
                sprintf('Registration %s is not in Interested status.', $registration->id),
            );
        }

        return DB::transaction(function () use ($registration): EventRegistration {
            $scope = $this->scopeResolver->resolve(
                $registration->session ?? $registration->occurrence ?? $registration->event,
            );
            $this->lockScope->handle($scope);

            $registration->refresh();

            if (! $registration->status instanceof Interested) {
                throw new NotInterestedRegistrationException(
                    sprintf('Registration %s is not in Interested status.', $registration->id),
                );
            }

            $capacityRemaining = $this->capacityRemaining($registration);

            if ($capacityRemaining !== null && $capacityRemaining < 1) {
                $scopeLabel = $this->capacityScopeLabel($registration);
                $scopeId = $this->capacityScopeId($registration) ?? 'unknown';

                throw new EventCapacityExceededException(
                    sprintf(
                        '%s %s is at capacity. Cannot promote registration %s.',
                        $scopeLabel,
                        (string) $scopeId,
                        $registration->id,
                    ),
                );
            }

            $registration->transitionStatus(Confirmed::class);

            $registration->refresh();

            if (
                $registration->session?->shouldIssuePassesForFree()
                ?? $registration->occurrence?->shouldIssuePassesForFree()
                ?? $registration->event->shouldIssuePassesForFree()
            ) {
                $this->issuePasses->handle($registration);
            }

            return $registration;
        });
    }

    private function capacityRemaining(EventRegistration $registration): ?int
    {
        $sessionRemaining = $registration->session?->capacityRemaining();

        if ($sessionRemaining !== null) {
            return $sessionRemaining;
        }

        return $registration->occurrence?->capacityRemaining();
    }

    private function capacityScopeLabel(EventRegistration $registration): string
    {
        if ($registration->session !== null && $registration->session->capacity !== null) {
            return 'Session';
        }

        if ($registration->occurrence !== null && $registration->occurrence->capacity !== null) {
            return 'Occurrence';
        }

        return 'Registration';
    }

    private function capacityScopeId(EventRegistration $registration): ?string
    {
        if ($registration->session !== null && $registration->session->capacity !== null) {
            return $registration->session->getKey();
        }

        if ($registration->occurrence !== null && $registration->occurrence->capacity !== null) {
            return $registration->occurrence->getKey();
        }

        return null;
    }
}
