<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final readonly class DeleteScheduleRecordAction
{
    public function handle(string $scope, string $recordId, mixed $actor): bool
    {
        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException;
        }

        $class = match ($scope) {
            'occurrence' => ModelResolver::occurrenceClass(),
            'session' => ModelResolver::sessionClass(),
            default => throw new InvalidArgumentException('Unknown schedule scope.'),
        };

        return DB::transaction(function () use ($class, $recordId, $actor): bool {
            $record = $class::query()->whereKey($recordId)->lockForUpdate()->firstOrFail();
            EventWriteGuard::findOrFail((string) $record->getAttribute('event_id'));
            Gate::forUser($actor)->authorize('delete', $record);

            return (bool) $record->delete();
        });
    }
}
