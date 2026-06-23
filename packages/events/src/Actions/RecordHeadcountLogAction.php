<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Contracts\EventRegistrationScopeResolver;
use AIArmada\Events\Enums\OpenDoorMode;
use AIArmada\Events\Exceptions\NotOpenDoorEventException;
use AIArmada\Events\Exceptions\WrongOpenDoorModeException;
use AIArmada\Events\Models\EventHeadcountLog;
use AIArmada\Events\Support\EventRegistrationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

final class RecordHeadcountLogAction
{
    public function __construct(
        private readonly EventRegistrationScopeResolver $scopeResolver,
    ) {}

    public function execute(
        Model $target,
        int $count,
        ?string $intervalLabel = null,
        ?Model $recordedBy = null,
        ?string $notes = null,
    ): EventHeadcountLog {
        $scope = $this->scopeResolver->resolve($target);
        OwnerWriteGuard::findOrFailForOwner($scope->event::class, $scope->event->id);

        $this->validateOpenDoorMode($scope);

        return EventHeadcountLog::create([
            'event_id' => $scope->event->id,
            'event_occurrence_id' => $scope->occurrence?->id,
            'event_session_id' => $scope->session?->id,
            'count' => max(1, $count),
            'recorded_at' => CarbonImmutable::now(),
            'interval_label' => $intervalLabel,
            'recorded_by_type' => $recordedBy?->getMorphClass(),
            'recorded_by_id' => $recordedBy?->getKey(),
            'notes' => $notes,
        ]);
    }

    private function validateOpenDoorMode(EventRegistrationScope $scope): void
    {
        if (! $scope->isOpenDoor()) {
            throw new NotOpenDoorEventException(
                sprintf('Event %s is not set to open-door mode.', $scope->event->id),
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
