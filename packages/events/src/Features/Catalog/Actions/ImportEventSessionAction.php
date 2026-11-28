<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Contracts\EventImportScopeValidator;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\OccurrenceStatus;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class ImportEventSessionAction
{
    public function __construct(
        private EventImportScopeValidator $scope,
        private CreateEventSessionAction $create,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, mixed $actor): EventSession
    {
        $eventId = $data['event_id'] ?? null;
        $occurrenceId = $data['event_occurrence_id'] ?? null;

        if (! is_string($occurrenceId) || $occurrenceId === '') {
            throw ValidationException::withMessages(['event_occurrence_id' => 'An occurrence is required.']);
        }

        $this->scope->validate($eventId, $occurrenceId, $actor, 'update');

        $occurrence = ModelResolver::occurrenceClass()::query()
            ->whereKey($occurrenceId)->where('event_id', $eventId)->firstOrFail();

        if (! $occurrence instanceof EventOccurrence) {
            throw new InvalidArgumentException('Invalid occurrence model.');
        }

        if (! is_string($data['status'] ?? null) || ! array_key_exists($data['status'], OccurrenceStatus::options())) {
            throw ValidationException::withMessages(['status' => 'Invalid session status.']);
        }

        Gate::forUser($actor)->authorize('create', ModelResolver::sessionClass());
        Gate::forUser($actor)->authorize('update', $occurrence);

        try {
            return $this->create->handle($occurrence, Arr::only($data, [
                'title', 'slug', 'summary', 'starts_at', 'ends_at', 'timezone', 'status', 'capacity', 'sort_order',
            ]));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['title' => $exception->getMessage()]);
        }
    }
}
