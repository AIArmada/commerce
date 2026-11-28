<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Actions;

use AIArmada\Events\Features\Venues\Models\VenueSpace;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ManageVenueSpaceAction
{
    /** @param array<string, mixed> $data */
    public function save(?string $spaceId, array $data, mixed $actor): VenueSpace
    {
        return DB::transaction(function () use ($spaceId, $data, $actor): VenueSpace {
            $space = $this->resolve($spaceId);
            $this->authorize($actor, $spaceId === null ? 'create' : 'update', $spaceId === null ? $space::class : $space);
            $space->fill(Arr::only($data, $space->getFillable()));

            if ($space->capacity !== null && $space->capacity < 1) {
                throw ValidationException::withMessages(['capacity' => 'Capacity must be at least one.']);
            }

            $space->save();

            return $space;
        });
    }

    public function delete(string $spaceId, mixed $actor): bool
    {
        return DB::transaction(function () use ($spaceId, $actor): bool {
            $space = $this->resolve($spaceId);
            $this->authorize($actor, 'delete', $space);

            if ($space->eventLocations()->withoutGlobalScope(EventWriteGuard::OWNER_SCOPE)->exists()) {
                throw ValidationException::withMessages(['venue_space_id' => 'Remove event location assignments before deleting this space.']);
            }

            return (bool) $space->delete();
        });
    }

    private function resolve(?string $spaceId): VenueSpace
    {
        $class = ModelResolver::venueSpaceClass();
        $space = $spaceId === null ? new $class : $class::query()->whereKey($spaceId)->lockForUpdate()->firstOrFail();

        if (! $space instanceof VenueSpace) {
            throw new LogicException('Configured venue space model must extend VenueSpace.');
        }

        return $space;
    }

    /** @param class-string<VenueSpace>|VenueSpace $subject */
    private function authorize(mixed $actor, string $ability, string | VenueSpace $subject): void
    {
        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException;
        }

        Gate::forUser($actor)->authorize($ability, $subject);
    }
}
