<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Attendance\Actions;

use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Data\EventRegistrationScopeData;
use AIArmada\Events\Data\OpenDoorMode;
use AIArmada\Events\Exceptions\NotOpenDoorEventException;
use AIArmada\Events\Exceptions\WrongOpenDoorModeException;
use AIArmada\Events\Features\Attendance\Models\EventHeadcountLog;
use AIArmada\Events\Platform\EventWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class RecordHeadcountLogAction
{
    public function __construct(
        private readonly EventRegistrationScopeResolver $scopeResolver,
        private readonly EnsureOpenDoorLifecycleAction $lifecycle,
    ) {}

    public function execute(
        string $scopeType,
        string $scopeId,
        int $count,
        ?string $intervalLabel = null,
        ?Model $recordedBy = null,
        ?string $notes = null,
    ): string {
        $scope = $this->scopeResolver->resolve($scopeType, $scopeId);

        return DB::transaction(function () use ($scope, $count, $intervalLabel, $recordedBy, $notes): string {
            EventWriteGuard::findOrFail($scope->eventId);

            $this->lifecycle->handle($scope);
            $this->validateOpenDoorMode($scope);

            $log = EventHeadcountLog::query()->create([
                'event_id' => $scope->eventId,
                'event_occurrence_id' => $scope->occurrenceId,
                'event_session_id' => $scope->sessionId,
                'count' => max(1, $count),
                'recorded_at' => CarbonImmutable::now(),
                'interval_label' => $intervalLabel,
                'recorded_by_type' => $recordedBy?->getMorphClass(),
                'recorded_by_id' => $recordedBy?->getKey(),
                'notes' => $notes,
            ]);

            return (string) $log->getKey();
        });
    }

    private function validateOpenDoorMode(EventRegistrationScopeData $scope): void
    {
        if (! $scope->isOpenDoor()) {
            throw new NotOpenDoorEventException(
                sprintf('Event %s is not set to open-door mode.', $scope->eventId),
            );
        }

        $mode = config('events.features.free_only.open_door_mode', 'block');

        if ($mode !== OpenDoorMode::Headcount->value) {
            throw new WrongOpenDoorModeException(
                sprintf(
                    'Expected open_door_mode to be "%s", got "%s".',
                    OpenDoorMode::Headcount->value,
                    $mode,
                ),
            );
        }
    }
}
