<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Queries;

use AIArmada\Events\Contracts\EventImportScopeValidator;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class OwnerScopedEventImportValidator implements EventImportScopeValidator
{
    public function validate(mixed $eventId, mixed $occurrenceId, mixed $actor, string $ability): void
    {
        if (! is_string($eventId) || ! Str::isUuid($eventId)) {
            $this->invalidEvent();
        }

        try {
            $event = EventWriteGuard::findOrFail($eventId);

            if (! $actor instanceof Authenticatable) {
                $this->invalidEvent();
            }

            Gate::forUser($actor)->authorize($ability, $event);
        } catch (AuthorizationException | ModelNotFoundException) {
            $this->invalidEvent();
        }

        if ($occurrenceId === null || $occurrenceId === '') {
            return;
        }

        $occurrenceClass = ModelResolver::occurrenceClass();

        if (! is_string($occurrenceId)
            || ! Str::isUuid($occurrenceId)
            || ! $occurrenceClass::query()->whereKey($occurrenceId)->where('event_id', $event->getKey())->exists()) {
            throw ValidationException::withMessages([
                'event_occurrence_id' => 'The selected occurrence does not belong to the selected event.',
            ]);
        }
    }

    private function invalidEvent(): never
    {
        throw ValidationException::withMessages([
            'event_id' => 'The selected event does not exist in the current scope.',
        ]);
    }
}
