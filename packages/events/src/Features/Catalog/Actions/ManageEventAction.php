<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\States\EventStatus\PendingReview;
use AIArmada\Events\Features\Catalog\States\EventStatus\Scheduled;
use AIArmada\Events\Platform\EventContentNormalizer;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ManageEventAction
{
    public function __construct(private EventContentNormalizer $normalizer) {}

    /** @param array<string, mixed> $data */
    public function save(?string $eventId, array $data, mixed $actor): Event
    {
        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($eventId, $data, $actor): Event {
            $class = ModelResolver::eventClass();

            if ($eventId !== null) {
                EventWriteGuard::findOrFail($eventId);
            }

            $event = $eventId === null ? new $class : $class::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();

            if (! $event instanceof Event) {
                throw new LogicException('Configured event model must extend Event.');
            }

            Gate::forUser($actor)->authorize($eventId === null ? 'create' : 'update', $eventId === null ? $class : $event);

            $attributes = $this->normalizer->normalize(Arr::only($data, [
                'title', 'slug', 'summary', 'description', 'type', 'schedule_kind',
                'visibility', 'delivery_mode', 'timezone', 'pricing_mode',
                'registration_mode', 'issue_passes_for_free', 'metadata',
            ]));

            if ($eventId === null) {
                $event->visibility = Event::PUBLIC;
                $event->delivery_mode = Event::DELIVERY_PHYSICAL;
            }

            $event->fill($attributes);

            if (blank($event->title) || blank($event->slug)) {
                throw ValidationException::withMessages(['title' => 'An event title and slug are required.']);
            }

            $status = $data['status'] ?? 'draft';

            if ($eventId === null && ! in_array($status, ['draft', 'pending_review', 'scheduled'], true)) {
                throw ValidationException::withMessages(['status' => 'New events must begin in draft, pending review or scheduled status.']);
            }

            if ($eventId === null) {
                $event->status = Event::DRAFT;
            }

            $event->save();

            if ($eventId === null && $status !== 'draft') {
                $event->status->transitionTo($status === 'scheduled' ? Scheduled::class : PendingReview::class);
            }

            return $event;
        });
    }

    public function delete(string $eventId, mixed $actor): bool
    {
        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($eventId, $actor): bool {
            EventWriteGuard::findOrFail($eventId);
            $event = ModelResolver::eventClass()::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $event);

            return (bool) $event->delete();
        });
    }
}
