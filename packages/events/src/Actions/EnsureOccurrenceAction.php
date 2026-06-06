<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Enums\OccurrenceParticipationMode;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSeries;
use AIArmada\Events\Models\EventSpeaker;
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
        $timezone = $this->stringOrNull($event['default_timezone'] ?? null)
            ?? $this->configuredDefaultTimezone();
        $organizer = $event['organizer'] ?? null;

        $eventModel = Event::query()
            ->forOwner()
            ->updateOrCreate(
                [
                    'event_series_id' => $series->id,
                    'slug' => $this->requireString($event, 'slug'),
                ],
                [
                    'organizer_type' => $this->stringOrNull($event['organizer_type'] ?? null)
                        ?? ($organizer instanceof Model ? $organizer->getMorphClass() : null),
                    'organizer_id' => $this->stringOrNull($event['organizer_id'] ?? null)
                        ?? ($organizer instanceof Model ? (string) $organizer->getKey() : null),
                    'product_id' => $this->stringOrNull($event['product_id'] ?? null),
                    'name' => $this->requireString($event, 'name'),
                    'summary' => $this->stringOrNull($event['summary'] ?? null),
                    'description' => $this->stringOrNull($event['description'] ?? null),
                    'status' => $this->resolveEventStatus($event['status'] ?? null),
                    'moderation_status' => $this->resolveEventModerationStatus($event['moderation_status'] ?? null),
                    'visibility' => $this->resolveEventVisibility($event['visibility'] ?? null),
                    'default_duration_minutes' => $this->intOrNull($event['default_duration_minutes'] ?? null),
                    'default_timezone' => $timezone,
                    'published_at' => $this->dateTimeOrNull($event['published_at'] ?? null, $timezone),
                    'public_starts_at' => $this->dateTimeOrNull($event['public_starts_at'] ?? null, $timezone),
                    'public_ends_at' => $this->dateTimeOrNull($event['public_ends_at'] ?? null, $timezone),
                    'media_references' => $this->arrayOrNull($event['media_references'] ?? null)
                        ?? $this->arrayOrNull($event['media'] ?? null),
                    'taxonomy' => $this->arrayOrNull($event['taxonomy'] ?? null),
                    'search_keywords' => $this->stringOrNull($event['search_keywords'] ?? null),
                    'metadata' => $this->arrayOrNull($event['metadata'] ?? null),
                ],
            );

        $this->syncSpeakers($eventModel, $event);

        return $eventModel;
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
                    'location_type' => $this->stringOrNull($venue['location_type'] ?? null) ?? 'physical',
                    'latitude' => $this->decimalStringOrNull($venue['latitude'] ?? null),
                    'longitude' => $this->decimalStringOrNull($venue['longitude'] ?? null),
                    'map_url' => $this->stringOrNull($venue['map_url'] ?? null),
                    'external_id' => $this->stringOrNull($venue['external_id'] ?? null),
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
                    'participation_mode' => $this->resolveParticipationMode($occurrence['participation_mode'] ?? null),
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

    private function resolveEventModerationStatus(mixed $value): EventModerationStatus
    {
        if ($value instanceof EventModerationStatus) {
            return $value;
        }

        if (is_string($value) && EventModerationStatus::tryFrom($value) instanceof EventModerationStatus) {
            return EventModerationStatus::from($value);
        }

        $configuredStatus = config('events.defaults.event_moderation_status', EventModerationStatus::Approved->value);

        if (is_string($configuredStatus) && EventModerationStatus::tryFrom($configuredStatus) instanceof EventModerationStatus) {
            return EventModerationStatus::from($configuredStatus);
        }

        return EventModerationStatus::Approved;
    }

    private function resolveEventVisibility(mixed $value): EventVisibility
    {
        if ($value instanceof EventVisibility) {
            return $value;
        }

        if (is_string($value) && EventVisibility::tryFrom($value) instanceof EventVisibility) {
            return EventVisibility::from($value);
        }

        $configuredVisibility = config('events.defaults.event_visibility', EventVisibility::Public->value);

        if (is_string($configuredVisibility) && EventVisibility::tryFrom($configuredVisibility) instanceof EventVisibility) {
            return EventVisibility::from($configuredVisibility);
        }

        return EventVisibility::Public;
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

    private function resolveParticipationMode(mixed $value): OccurrenceParticipationMode
    {
        if ($value instanceof OccurrenceParticipationMode) {
            return $value;
        }

        if (is_string($value) && OccurrenceParticipationMode::tryFrom($value) instanceof OccurrenceParticipationMode) {
            return OccurrenceParticipationMode::from($value);
        }

        $configuredMode = config('events.defaults.occurrence_participation_mode', OccurrenceParticipationMode::RegistrationRequired->value);

        if (is_string($configuredMode) && OccurrenceParticipationMode::tryFrom($configuredMode) instanceof OccurrenceParticipationMode) {
            return OccurrenceParticipationMode::from($configuredMode);
        }

        return OccurrenceParticipationMode::RegistrationRequired;
    }

    private function dateTimeOrNull(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, $timezone)->utc();
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    private function syncSpeakers(Event $event, array $eventPayload): void
    {
        if (! array_key_exists('speakers', $eventPayload)) {
            return;
        }

        $speakers = $eventPayload['speakers'];

        if (! is_array($speakers)) {
            return;
        }

        EventSpeaker::query()
            ->where('event_id', $event->id)
            ->delete();

        foreach (array_values($speakers) as $index => $speakerPayload) {
            if (! is_array($speakerPayload)) {
                continue;
            }

            $speaker = $speakerPayload['speaker'] ?? null;

            EventSpeaker::query()->create([
                'event_id' => $event->id,
                'speaker_type' => $this->stringOrNull($speakerPayload['speaker_type'] ?? null)
                    ?? ($speaker instanceof Model ? $speaker->getMorphClass() : null),
                'speaker_id' => $this->stringOrNull($speakerPayload['speaker_id'] ?? null)
                    ?? ($speaker instanceof Model ? (string) $speaker->getKey() : null),
                'display_name' => $this->stringOrNull($speakerPayload['display_name'] ?? null)
                    ?? ($speaker instanceof Model ? $this->stringOrNull($speaker->getAttribute('name')) : null),
                'role' => $this->stringOrNull($speakerPayload['role'] ?? null),
                'biography' => $this->stringOrNull($speakerPayload['biography'] ?? null),
                'order_column' => $this->intOrNull($speakerPayload['order_column'] ?? null) ?? $index + 1,
                'metadata' => $this->arrayOrNull($speakerPayload['metadata'] ?? null),
            ]);
        }
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

    private function decimalStringOrNull(mixed $value): ?string
    {
        return is_numeric($value) ? (string) $value : null;
    }

    private function configuredDefaultTimezone(): string
    {
        $timezone = config('events.timezone.default');

        if (is_string($timezone) && mb_trim($timezone) !== '') {
            return mb_trim($timezone);
        }

        return 'UTC';
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
