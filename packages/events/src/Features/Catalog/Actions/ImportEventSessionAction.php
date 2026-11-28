<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Contracts\EventImportScopeValidator;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\OccurrenceStatus;
use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
        $data = Validator::make($data, [
            'event_id' => ['required', 'uuid'],
            'event_occurrence_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'timezone' => ['required', 'timezone'],
            'status' => ['required', Rule::in(array_keys(OccurrenceStatus::options()))],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
        ])->validate();

        $eventId = $data['event_id'];
        $occurrenceId = $data['event_occurrence_id'];

        $this->scope->validate($eventId, $occurrenceId, $actor, 'update');

        $occurrence = ModelResolver::occurrenceClass()::query()
            ->whereKey($occurrenceId)->where('event_id', $eventId)->firstOrFail();

        if (! $occurrence instanceof EventOccurrence) {
            throw new InvalidArgumentException('Invalid occurrence model.');
        }

        Gate::forUser($actor)->authorize('create', ModelResolver::sessionClass());
        Gate::forUser($actor)->authorize('update', $occurrence);

        $data['starts_at'] = CarbonImmutable::parse($data['starts_at'], $data['timezone'])->utc();
        $data['ends_at'] = CarbonImmutable::parse($data['ends_at'], $data['timezone'])->utc();

        if ($data['ends_at']->lessThanOrEqualTo($data['starts_at'])) {
            throw ValidationException::withMessages(['ends_at' => 'Session end time must be after the start time.']);
        }

        unset($data['event_id'], $data['event_occurrence_id']);

        try {
            return $this->create->handle($occurrence, $data);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['title' => $exception->getMessage()]);
        }
    }
}
