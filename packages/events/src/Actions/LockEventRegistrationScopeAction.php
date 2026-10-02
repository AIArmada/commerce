<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Support\EventRegistrationScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Serializes capacity-sensitive registration writes for one event scope.
 *
 * Locks run parent-first (event, occurrence, session) so sibling sessions
 * contending on their shared occurrence aggregate serialize in one order.
 * Each query keeps its model global scopes, preserving the events owner
 * boundary, and refreshes the in-memory row so capacity checks after the
 * lock read current configured capacity.
 */
final class LockEventRegistrationScopeAction
{
    public function handle(EventRegistrationScope $scope): void
    {
        $this->lock($scope->event);

        if ($scope->occurrence !== null) {
            $this->lock($scope->occurrence);
        }

        if ($scope->session !== null) {
            $this->lock($scope->session);
        }
    }

    private function lock(Model $model): void
    {
        $fresh = $model->newQuery()
            ->whereKey($model->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $model->setRawAttributes($fresh->getAttributes(), true);
    }
}
