<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Archived;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\OccurrenceStatus;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class ArchiveScheduleRecordAction
{
    public function __construct(
        private EventLifecycleWorkflow $lifecycle,
        private DeleteEventSessionAction $archiveSession,
    ) {}

    public function handle(string $scope, string $recordId, mixed $actor): void
    {
        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException;
        }

        $class = match ($scope) {
            'occurrence' => ModelResolver::occurrenceClass(),
            'session' => ModelResolver::sessionClass(),
            default => throw new InvalidArgumentException('Unknown schedule scope.'),
        };

        DB::transaction(function () use ($class, $scope, $recordId, $actor): void {
            $record = $class::query()->whereKey($recordId)->lockForUpdate()->firstOrFail();
            EventWriteGuard::findOrFail((string) $record->getAttribute('event_id'));
            Gate::forUser($actor)->authorize('delete', $record);

            $status = $record->getAttribute('status');

            if ($status instanceof Archived) {
                return;
            }

            if (! $status instanceof OccurrenceStatus || ! $status->canTransitionTo(Archived::class)) {
                throw ValidationException::withMessages(['status' => 'This schedule cannot be archived in its current state.']);
            }

            if ($scope === 'session' && $record instanceof EventSession) {
                $this->archiveSession->handle($record);

                return;
            }

            $this->lifecycle->archive($scope, $recordId);
        });
    }
}
