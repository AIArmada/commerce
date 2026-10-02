<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Enums\ScheduleKind;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Support\EventWriteGuard;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Create or update the primary occurrence of an event.
 *
 * Published, postponed, delayed, and rescheduled occurrences are rescheduled
 * through the lifecycle workflow so the transition is recorded, including
 * open-ended schedules with an unknown end. Terminal occurrences (cancelled,
 * completed, archived) and live occurrences refuse schedule changes.
 * Identical re-syncs are no-ops that still apply non-schedule attributes.
 */
final class SyncPrimaryEventOccurrenceAction
{
    public function __construct(
        private readonly CreateEventOccurrenceAction $createOccurrence,
        private readonly UpdateEventOccurrenceAction $updateOccurrence,
        private readonly EventLifecycleWorkflow $lifecycleWorkflow,
    ) {}

    /**
     * @param  array{schedule_kind?: ScheduleKind|string, title?: ?string, slug?: ?string, starts_at?: DateTimeInterface|string|null, ends_at?: DateTimeInterface|string|null, timezone?: ?string, visibility?: ?string, delivery_mode?: ?string, capacity?: ?int, metadata?: mixed}  $attributes
     */
    public function handle(Event $event, array $attributes = []): ?EventOccurrence
    {
        $event = EventWriteGuard::findOrFail($event->getKey());

        return DB::transaction(function () use ($event, $attributes): ?EventOccurrence {
            if (array_key_exists('schedule_kind', $attributes) && $attributes['schedule_kind'] !== null) {
                $event->schedule_kind = $this->resolveScheduleKind($attributes['schedule_kind']);
                $event->save();
            }

            if (! array_key_exists('starts_at', $attributes) || blank($attributes['starts_at'])) {
                return $this->primaryOccurrence($event);
            }

            $occurrence = $this->primaryOccurrence($event);

            $timezone = $this->resolveTimezone($attributes, $occurrence, $event);
            $startsAt = $this->toImmutable($attributes['starts_at'], 'starts_at', $timezone)->setTimezone('UTC');

            $endsOmitted = ! array_key_exists('ends_at', $attributes) || $attributes['ends_at'] === '';
            $endsAt = $endsOmitted || $attributes['ends_at'] === null
                ? null
                : $this->toImmutable($attributes['ends_at'], 'ends_at', $timezone)->setTimezone('UTC');

            if ($endsAt instanceof CarbonImmutable && $endsAt->lessThanOrEqualTo($startsAt)) {
                throw new InvalidArgumentException('Occurrence end time must be after the start time.');
            }

            if (! $occurrence instanceof EventOccurrence) {
                return $this->createPrimary($event, $attributes, $startsAt, $endsAt, $endsOmitted, $timezone);
            }

            $effectiveEndsAt = $endsOmitted ? $this->storedEndsAt($occurrence) : $endsAt;

            if ($effectiveEndsAt instanceof CarbonImmutable && $effectiveEndsAt->lessThanOrEqualTo($startsAt)) {
                throw new InvalidArgumentException('Occurrence end time must be after the start time.');
            }

            $status = (string) $occurrence->status->getValue();

            if (in_array($status, [EventOccurrence::CANCELLED, EventOccurrence::COMPLETED, EventOccurrence::ARCHIVED], true)) {
                $this->assertTerminalScheduleUnchanged($occurrence, $startsAt, $effectiveEndsAt, $timezone);

                return $this->applyNonScheduleAttributes($occurrence, $attributes);
            }

            if ($status === 'live') {
                if ($this->scheduleChanged($occurrence, $startsAt, $effectiveEndsAt, $timezone)) {
                    throw new InvalidArgumentException('Live occurrences cannot be rescheduled through sync.');
                }

                return $this->applyNonScheduleAttributes($occurrence, $attributes);
            }

            if (in_array($status, [EventOccurrence::PUBLISHED, EventOccurrence::POSTPONED, EventOccurrence::DELAYED, EventOccurrence::RESCHEDULED], true)) {
                if (! $this->scheduleChanged($occurrence, $startsAt, $effectiveEndsAt, $timezone)) {
                    return $this->applyNonScheduleAttributes($occurrence, $attributes);
                }

                $rescheduled = $this->lifecycleWorkflow->reschedule($occurrence, $startsAt, $effectiveEndsAt, [
                    'timezone' => $timezone,
                ]);

                $result = $rescheduled instanceof EventOccurrence ? $rescheduled : $occurrence;

                return $this->applyNonScheduleAttributes($result, $attributes);
            }

            $update = ['starts_at' => $startsAt, 'timezone' => $timezone];

            if (! $endsOmitted) {
                $update['ends_at'] = $endsAt;
            }

            foreach (['title', 'slug', 'visibility', 'delivery_mode', 'capacity', 'metadata'] as $key) {
                if (array_key_exists($key, $attributes) && $attributes[$key] !== null) {
                    $update[$key] = $attributes[$key];
                }
            }

            return $this->updateOccurrence->handle($occurrence, $update)['occurrence'];
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPrimary(
        Event $event,
        array $attributes,
        CarbonImmutable $startsAt,
        ?CarbonImmutable $endsAt,
        bool $endsOmitted,
        string $timezone,
    ): EventOccurrence {
        $create = [
            'title' => $attributes['title'] ?? $event->title,
            'starts_at' => $startsAt,
            'timezone' => $timezone,
        ];

        if (! $endsOmitted) {
            $create['ends_at'] = $endsAt;
        }

        foreach (['slug', 'visibility', 'delivery_mode', 'capacity', 'metadata'] as $key) {
            if (array_key_exists($key, $attributes) && $attributes[$key] !== null && $attributes[$key] !== '') {
                $create[$key] = $attributes[$key];
            }
        }

        return $this->createOccurrence->handle($event, $create);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveTimezone(array $attributes, ?EventOccurrence $occurrence, Event $event): string
    {
        $candidate = $attributes['timezone'] ?? null;

        if (is_string($candidate) && $candidate !== '') {
            return $candidate;
        }

        if ($occurrence instanceof EventOccurrence && is_string($occurrence->timezone) && $occurrence->timezone !== '') {
            return $occurrence->timezone;
        }

        if (is_string($event->timezone ?? null) && $event->timezone !== '') {
            return $event->timezone;
        }

        $configured = config('events.defaults.timezone', 'UTC');

        return is_string($configured) && $configured !== '' ? $configured : 'UTC';
    }

    private function storedEndsAt(EventOccurrence $occurrence): ?CarbonImmutable
    {
        $endsAt = $occurrence->ends_at;

        if ($endsAt === null) {
            return null;
        }

        return $endsAt instanceof CarbonImmutable
            ? $endsAt
            : CarbonImmutable::createFromInterface($endsAt);
    }

    private function scheduleChanged(
        EventOccurrence $occurrence,
        CarbonImmutable $startsAt,
        ?CarbonImmutable $effectiveEndsAt,
        string $timezone,
    ): bool {
        if ((int) $occurrence->starts_at?->timestamp !== $startsAt->timestamp) {
            return true;
        }

        $currentEnds = $occurrence->ends_at;

        if (($currentEnds === null) !== ($effectiveEndsAt === null)) {
            return true;
        }

        if ($currentEnds !== null
            && $currentEnds->getTimestamp() !== $effectiveEndsAt?->getTimestamp()) {
            return true;
        }

        return (string) $occurrence->timezone !== $timezone;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyNonScheduleAttributes(EventOccurrence $occurrence, array $attributes): EventOccurrence
    {
        $update = [];

        foreach (['title', 'slug', 'visibility', 'delivery_mode', 'capacity', 'metadata'] as $key) {
            if (array_key_exists($key, $attributes) && $attributes[$key] !== null) {
                $update[$key] = $attributes[$key];
            }
        }

        if ($update === []) {
            return $occurrence;
        }

        return $this->updateOccurrence->handle($occurrence, $update)['occurrence'];
    }

    private function resolveScheduleKind(mixed $value): ScheduleKind
    {
        if ($value instanceof ScheduleKind) {
            return $value;
        }

        if (is_string($value)) {
            return ScheduleKind::tryFrom($value)
                ?? throw new InvalidArgumentException('Unknown schedule kind.');
        }

        throw new InvalidArgumentException('Unknown schedule kind.');
    }

    private function primaryOccurrence(Event $event): ?EventOccurrence
    {
        $event->unsetRelation('primaryOccurrence');

        return $event->primaryOccurrence;
    }

    private function toImmutable(mixed $value, string $field, string $timezone): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::createFromInterface($value);
        }

        if (! is_string($value) || mb_trim($value) === '') {
            throw new InvalidArgumentException("Occurrence {$field} must be a non-empty datetime.");
        }

        return CarbonImmutable::parse($value, $timezone);
    }

    private function assertTerminalScheduleUnchanged(
        EventOccurrence $occurrence,
        CarbonImmutable $startsAt,
        ?CarbonImmutable $effectiveEndsAt,
        string $timezone,
    ): void {
        if ($this->scheduleChanged($occurrence, $startsAt, $effectiveEndsAt, $timezone)) {
            throw new InvalidArgumentException('Terminal occurrences cannot be rescheduled through sync.');
        }
    }
}
