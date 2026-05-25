<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSeries;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class EnsureOccurrenceAction
{
    /**
     * @param  array<string, mixed>  $series
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>|null  $venue
     * @param  array<string, mixed>  $occurrence
     */
    public function handle(array $series, array $event, ?array $venue, array $occurrence, ?Model $owner = null): Occurrence
    {
        return OwnerContext::withOwner($owner, function () use ($series, $event, $venue, $occurrence): Occurrence {
            $eventSeries = $this->ensureSeries($series);
            $eventModel = $this->ensureEvent($event, $eventSeries);
            $venueModel = $venue !== null ? $this->ensureVenue($venue) : null;

            return $this->ensureOccurrence($eventModel, $venueModel, $occurrence);
        });
    }

    /**
     * @param  array<string, mixed>  $series
     */
    private function ensureSeries(array $series): EventSeries
    {
        return EventSeries::query()
            ->forOwner()
            ->updateOrCreate(
                ['slug' => $this->requireString($series, 'slug')],
                [
                    'name' => $this->requireString($series, 'name'),
                    'description' => $this->stringOrNull($series['description'] ?? null),
                    'is_active' => (bool) ($series['is_active'] ?? true),
                    'metadata' => $this->arrayOrNull($series['metadata'] ?? null),
                ],
            );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function ensureEvent(array $event, EventSeries $series): Event
    {
        return Event::query()
            ->forOwner()
            ->updateOrCreate(
                [
                    'event_series_id' => $series->id,
                    'slug' => $this->requireString($event, 'slug'),
                ],
                [
                    'product_id' => $this->stringOrNull($event['product_id'] ?? null),
                    'name' => $this->requireString($event, 'name'),
                    'summary' => $this->stringOrNull($event['summary'] ?? null),
                    'description' => $this->stringOrNull($event['description'] ?? null),
                    'status' => $this->resolveEventStatus($event['status'] ?? null),
                    'default_duration_minutes' => $this->intOrNull($event['default_duration_minutes'] ?? null),
                    'default_timezone' => $this->stringOrNull($event['default_timezone'] ?? null),
                    'metadata' => $this->arrayOrNull($event['metadata'] ?? null),
                ],
            );
    }

    /**
     * @param  array<string, mixed>  $venue
     */
    private function ensureVenue(array $venue): Venue
    {
        return Venue::query()
            ->forOwner()
            ->updateOrCreate(
                ['slug' => $this->requireString($venue, 'slug')],
                [
                    'name' => $this->requireString($venue, 'name'),
                    'contact_name' => $this->stringOrNull($venue['contact_name'] ?? null),
                    'contact_email' => $this->stringOrNull($venue['contact_email'] ?? null),
                    'contact_phone' => $this->stringOrNull($venue['contact_phone'] ?? null),
                    'line1' => $this->stringOrNull($venue['line1'] ?? null),
                    'line2' => $this->stringOrNull($venue['line2'] ?? null),
                    'city' => $this->stringOrNull($venue['city'] ?? null),
                    'state' => $this->stringOrNull($venue['state'] ?? null),
                    'postcode' => $this->stringOrNull($venue['postcode'] ?? null),
                    'country' => $this->stringOrNull($venue['country'] ?? null) ?? 'MY',
                    'timezone' => $this->stringOrNull($venue['timezone'] ?? null),
                    'notes' => $this->stringOrNull($venue['notes'] ?? null),
                    'metadata' => $this->arrayOrNull($venue['metadata'] ?? null),
                ],
            );
    }

    /**
     * @param  array<string, mixed>  $occurrence
     */
    private function ensureOccurrence(Event $event, ?Venue $venue, array $occurrence): Occurrence
    {
        $timezone = $this->stringOrNull($occurrence['timezone'] ?? null)
            ?? $event->default_timezone
            ?? $venue?->timezone
            ?? 'UTC';

        $startsAt = $this->dateTimeOrNull($occurrence['starts_at'] ?? null, $timezone);

        if ($startsAt === null) {
            throw new InvalidArgumentException('The [starts_at] field is required.');
        }

        $endsAt = $this->dateTimeOrNull($occurrence['ends_at'] ?? null, $timezone)
            ?? ($event->default_duration_minutes !== null ? $startsAt->addMinutes($event->default_duration_minutes) : null);

        return Occurrence::query()
            ->forOwner()
            ->updateOrCreate(
                [
                    'event_id' => $event->id,
                    'starts_at' => $startsAt,
                ],
                [
                    'venue_id' => $venue?->id,
                    'product_id' => $this->stringOrNull($occurrence['product_id'] ?? null),
                    'variant_id' => $this->stringOrNull($occurrence['variant_id'] ?? null),
                    'name' => $this->stringOrNull($occurrence['name'] ?? null),
                    'status' => $this->resolveOccurrenceStatus($occurrence['status'] ?? null),
                    'capacity' => $this->intOrNull($occurrence['capacity'] ?? null),
                    'ends_at' => $endsAt,
                    'timezone' => $timezone,
                    'registration_opens_at' => $this->dateTimeOrNull($occurrence['registration_opens_at'] ?? null, $timezone),
                    'registration_closes_at' => $this->dateTimeOrNull($occurrence['registration_closes_at'] ?? null, $timezone),
                    'check_in_opens_at' => $this->dateTimeOrNull($occurrence['check_in_opens_at'] ?? null, $timezone),
                    'check_in_closes_at' => $this->dateTimeOrNull($occurrence['check_in_closes_at'] ?? null, $timezone),
                    'metadata' => $this->arrayOrNull($occurrence['metadata'] ?? null),
                ],
            );
    }

    private function resolveEventStatus(mixed $value): EventStatus
    {
        if ($value instanceof EventStatus) {
            return $value;
        }

        if (is_string($value) && EventStatus::tryFrom($value) instanceof EventStatus) {
            return EventStatus::from($value);
        }

        return EventStatus::Active;
    }

    private function resolveOccurrenceStatus(mixed $value): OccurrenceStatus
    {
        if ($value instanceof OccurrenceStatus) {
            return $value;
        }

        if (is_string($value) && OccurrenceStatus::tryFrom($value) instanceof OccurrenceStatus) {
            return OccurrenceStatus::from($value);
        }

        return OccurrenceStatus::Scheduled;
    }

    private function dateTimeOrNull(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, $timezone);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requireString(array $payload, string $key): string
    {
        $value = $this->stringOrNull($payload[$key] ?? null);

        if ($value === null) {
            throw new InvalidArgumentException(sprintf('The [%s] field is required.', $key));
        }

        return $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $resolved = mb_trim($value);

        return $resolved !== '' ? $resolved : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function arrayOrNull(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $filtered = array_filter($value, static fn (mixed $item): bool => match (true) {
            $item === null => false,
            is_string($item) => $item !== '',
            default => true,
        });

        return $filtered !== [] ? $filtered : null;
    }
}
