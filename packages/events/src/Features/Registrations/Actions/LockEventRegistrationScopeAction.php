<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Registrations\Actions;

use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Serializes capacity-sensitive registration writes for one event scope.
 *
 * Locks run parent-first (event, occurrence, session) so sibling sessions
 * contending on their shared occurrence aggregate serialize in one order.
 */
final class LockEventRegistrationScopeAction
{
    public function handle(EventRegistrationScopeData $scope): void
    {
        /** @var class-string<Model> $eventClass */
        $eventClass = ModelResolver::eventClass();
        $this->lock($eventClass, $scope->eventId);

        if ($scope->occurrenceId !== null) {
            /** @var class-string<Model> $occurrenceClass */
            $occurrenceClass = ModelResolver::occurrenceClass();
            $this->lock($occurrenceClass, $scope->occurrenceId);
        }

        if ($scope->sessionId !== null) {
            /** @var class-string<Model> $sessionClass */
            $sessionClass = ModelResolver::sessionClass();
            $this->lock($sessionClass, $scope->sessionId);
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function lock(string $modelClass, string $id): void
    {
        $modelClass::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
