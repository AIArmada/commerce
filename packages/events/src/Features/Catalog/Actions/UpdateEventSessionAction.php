<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Events\EventSessionUpdated;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\OccurrenceStatus as OccurrenceStatusState;
use AIArmada\Events\Features\Changes\Api\ChangesApi;
use AIArmada\Events\Platform\EventContentNormalizer;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class UpdateEventSessionAction
{
    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{changes: array<string, array{old: mixed, new: mixed}>, session: EventSession}
     */
    public function handle(EventSession $session, array $attributes): array
    {
        [$session, $changes, $original] = DB::transaction(function () use ($session, $attributes): array {
            /** @var EventSession $session */
            $session = $session->newQuery()
                ->withoutGlobalScope(EventWriteGuard::OWNER_SCOPE)
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $event = EventWriteGuard::findOrFail((string) $session->getAttribute('event_id'));

            $original = $session->getRawOriginal();

            $fillable = $session->getFillable();
            $allowed = array_intersect_key($attributes, array_flip($fillable));
            unset(
                $allowed['event_id'],
                $allowed['event_occurrence_id'],
                $allowed['published_at'],
                $allowed['delayed_at'],
                $allowed['postponed_at'],
                $allowed['rescheduled_at'],
                $allowed['cancelled_at'],
                $allowed['completed_at'],
                $allowed['archived_at'],
            );

            if (array_key_exists('title', $allowed) && blank($allowed['title'])) {
                throw new InvalidArgumentException('Session title is required.');
            }

            if (array_key_exists('title', $allowed)) {
                $allowed['title'] = $this->contentNormalizer->normalizeTitle((string) $allowed['title']);
            }

            if (array_key_exists('summary', $allowed)) {
                $allowed['summary'] = $this->contentNormalizer->normalizeSummary($allowed['summary'] !== null ? (string) $allowed['summary'] : null);
            }

            if (array_key_exists('description', $allowed)) {
                $allowed['description'] = $this->contentNormalizer->normalizeDescription($allowed['description'] !== null ? (string) $allowed['description'] : null);
            }

            if (array_key_exists('starts_at', $allowed) && blank($allowed['starts_at'])) {
                throw new InvalidArgumentException('Session start time is required.');
            }

            $startsAt = array_key_exists('starts_at', $allowed)
                ? CarbonImmutable::parse($allowed['starts_at'])
                : $session->starts_at;
            $endsAt = ! array_key_exists('ends_at', $allowed)
                ? $session->ends_at
                : ($allowed['ends_at'] === null ? null : CarbonImmutable::parse($allowed['ends_at']));

            if ($endsAt instanceof CarbonImmutable && $startsAt instanceof CarbonImmutable && $endsAt->lessThanOrEqualTo($startsAt)) {
                throw new InvalidArgumentException('Session end time must be after the start time.');
            }

            if (array_key_exists('visibility', $allowed) && blank($allowed['visibility'])) {
                $allowed['visibility'] = $session->occurrence?->visibility ?: ($event->getAttribute('visibility') ?: 'public');
            }

            if (array_key_exists('slug', $allowed) && ! blank($allowed['slug']) && $allowed['slug'] !== $session->slug) {
                $event->newQuery()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();

                $duplicate = ModelResolver::sessionClass()::query()
                    ->withoutGlobalScope(EventWriteGuard::OWNER_SCOPE)
                    ->where('event_id', $session->event_id)
                    ->whereKeyNot($session->getKey())
                    ->where('slug', $allowed['slug'])
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages(['slug' => 'This slug is already used by a session in this event.']);
                }
            }

            $statusValue = $allowed['status'] ?? null;
            unset($allowed['status']);

            if ($statusValue !== null && ! is_string($statusValue)) {
                throw new InvalidArgumentException('Session status must be a state name.');
            }

            $session->update($allowed);

            if ($statusValue !== null) {
                $this->transitionStatus($session, $statusValue);
            }

            $session->refresh();

            $current = $session->getAttributes();

            $changes = [];
            foreach ($allowed as $key => $newValue) {
                $oldValue = $original[$key] ?? null;
                $normalizedNewValue = $current[$key] ?? null;

                if ($oldValue !== $normalizedNewValue) {
                    $changes[$key] = ['old' => $oldValue, 'new' => $normalizedNewValue];
                }
            }

            if ($statusValue !== null && ($original['status'] ?? null) !== ($current['status'] ?? null)) {
                $changes['status'] = ['old' => $original['status'] ?? null, 'new' => $current['status'] ?? null];
            }

            return [$session, $changes, $original];
        });

        if ($changes !== []) {
            DB::afterCommit(static fn () => event(new EventSessionUpdated(
                (string) $session->getKey(),
                (string) $session->event_id,
                $changes,
            )));
        }

        if (isset($changes['status'])) {
            $changeType = match ($session->status->getValue()) {
                'published' => 'published',
                'delayed' => 'delayed',
                'cancelled' => 'cancelled',
                'postponed' => 'postponed',
                'rescheduled' => 'rescheduled',
                'completed' => 'completed',
                'archived' => 'archived',
                default => null,
            };

            if ($changeType !== null) {
                app(ChangesApi::class)->dispatchChange(
                    eventId: $session->event_id,
                    changeType: $changeType,
                    reason: $session->status_reason,
                    sessionId: $session->id,
                    occurrenceId: $session->event_occurrence_id,
                    oldValue: ['status' => $original['status'] ?? null],
                    newValue: ['status' => $session->status->getValue()],
                );
            }
        }

        return [
            'changes' => $changes,
            'session' => $session->fresh(),
        ];
    }

    private function transitionStatus(EventSession $session, string $statusValue): void
    {
        if ($session->status->getValue() === $statusValue) {
            return;
        }

        $stateClass = OccurrenceStatusState::resolveStateClass($statusValue);

        if (! is_string($stateClass) || ! is_a($stateClass, OccurrenceStatusState::class, true)) {
            throw new InvalidArgumentException(sprintf('Unknown session status [%s].', $statusValue));
        }

        $timestampField = match ($statusValue) {
            'published' => 'published_at',
            'delayed' => 'delayed_at',
            'postponed' => 'postponed_at',
            'rescheduled' => 'rescheduled_at',
            'cancelled' => 'cancelled_at',
            'completed' => 'completed_at',
            'archived' => 'archived_at',
            default => null,
        };

        if ($timestampField !== null) {
            $session->{$timestampField} = CarbonImmutable::now();
        }

        $session->status->transitionTo($stateClass);
    }
}
