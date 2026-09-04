<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Support\EventRegistrationScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Serializes capacity-sensitive registration writes for one event scope.
 */
final class LockEventRegistrationScopeAction
{
    public function handle(EventRegistrationScope $scope): void
    {
        $model = $scope->session ?? $scope->occurrence ?? $scope->event;

        $this->lock($model);
    }

    private function lock(Model $model): void
    {
        $model->newQuery()
            ->whereKey($model->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
