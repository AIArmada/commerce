<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventAddressable;
use AIArmada\Events\Contracts\EventScheduleResolver;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventStructure;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Enums\OccurrenceParticipationMode;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventPerson;
use AIArmada\Events\Models\EventSeries;
use AIArmada\Events\Models\EventSubLocation;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Support\Integration\EventAddressRegistry;
use AIArmada\Events\Support\Integration\EventAddressResolver;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EnsureOccurrenceAction
{
    public function __construct(
        private readonly EventScheduleResolver $scheduleResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $series
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $occurrence
     */
    public function handle(array $series, array $event, array $occurrence = [], ?Model $owner = null, mixed $address = null, mixed $sub_location = null): Occurrence
    {
        if ($occurrence === []) {
            throw new InvalidArgumentException('The [occurrence] payload is required.');
        }

        return OwnerContext::withOwner($owner, function () use ($series, $event, $occurrence, $address, $sub_location): Occurrence {
            $eventSeries = $this->ensureSeries($series);
            $eventModel = $this->ensureEvent($event, $eventSeries);
            $addressModel = $this->resolveAddress($address, $occurrence);
            $subLocationModel = $this->resolveSubLocation($sub_location, $occurrence);
            $schedule = $this->scheduleResolver->resolve(
                $series,
                $event,
                $this->buildScheduleLocationPayload($addressModel, $subLocationModel),
                $occurrence,
            ) ?? [];

            return $this->ensureOccurrence($eventModel, $addressModel, $subLocationModel, $occurrence, $schedule);
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
                    'status' => (bool) ($series['is_active'] ?? true) ? 'active' : 'archived',
                    'metadata' => $this->arrayOrNull($series['metadata'] ?? null),
                ],
            );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function ensureEvent(array $event, EventSeries $series, bool $isParentPayload = false): Event
    {
        $timezone = $this->stringOrNull($event['default_timezone'] ?? null)
            ?? $this->configuredDefaultTimezone();
        $organizer = $event['organizer'] ?? null;
        $parentEventId = $this->resolveParentEventId($event, $series, $isParentPayload);
        $structure = $this->resolveEventStructure(
            $event['structure'] ?? $event['kind'] ?? null,
            $parentEventId !== null,
            $isParentPayload,
        );

        $eventModel = Event::query()
            ->forOwner()
            ->updateOrCreate(
                [
                    'event_series_id' => $series->id,
                    'slug' => $this->resolveEventSlug($event),
                ],
                [
                    'organizer_type' => $this->stringOrNull($event['organizer_type'] ?? null)
                        ?? ($organizer instanceof Model ? $organizer->getMorphClass() : null),
                    'organizer_id' => $this->stringOrNull($event['organizer_id'] ?? null)
                        ?? ($organizer instanceof Model ? (string) $organizer->getKey() : null),
                    'product_id' => $this->stringOrNull($event['product_id'] ?? null),
                    'parent_event_id' => $parentEventId,
                    'name' => $this->requireString($event, 'name'),
                    'summary' => $this->stringOrNull($event['summary'] ?? null),
                    'description' => $this->stringOrNull($event['description'] ?? null),
                    'status' => $this->resolveEventStatus($event['status'] ?? null),
                    'moderation_status' => $this->resolveEventModerationStatus($event['moderation_status'] ?? null),
                    'visibility' => $this->resolveEventVisibility($event['visibility'] ?? null),
                    'structure' => $structure,
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
                    'registration_required' => $this->booleanOrNull($event['registration_required'] ?? null) ?? false,
                ],
            );

        $this->syncPeople($eventModel, $event);

        return $eventModel;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function resolveEventSlug(array $event): string
    {
        $explicit = $this->stringOrNull($event['slug'] ?? null);

        if ($explicit !== null) {
            return $explicit;
        }

        $sourceField = (string) config('events.slug.source_field', 'name');
        $maxLength = max(1, (int) config('events.slug.max_length', 60));
        $source = $this->stringOrNull($event[$sourceField] ?? $event['name'] ?? null);

        if ($source === null) {
            throw new InvalidArgumentException(sprintf(
                'The [slug] field is required (or a [%s] field must be provided to derive it).',
                $sourceField,
            ));
        }

        return Str::limit(Str::slug($source), $maxLength, '');
    }

    /**
     * @param  array<string, mixed>  $occurrence
     */
    private function resolveAddress(mixed $address, array $occurrence): ?Model
    {
        foreach ([
            $address,
            $occurrence['address'] ?? null,
            $this->addressPayloadFromOccurrence($occurrence),
        ] as $candidate) {
            $resolved = $this->resolveAddressCandidate($candidate, true);

            if ($resolved instanceof Model) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveAddressCandidate(mixed $candidate, bool $strict = true): ?Model
    {
        if ($candidate instanceof Model) {
            if ($candidate instanceof EventAddressable || ! $strict) {
                return $candidate;
            }

            throw new InvalidArgumentException(sprintf(
                'The [%s] address model must implement %s.',
                $candidate::class,
                EventAddressable::class,
            ));
        }

        if (! is_array($candidate)) {
            return null;
        }

        $addressType = $this->stringOrNull($candidate['address_type'] ?? $candidate['type'] ?? null);
        $addressId = $this->stringOrNull($candidate['address_id'] ?? $candidate['id'] ?? null);

        if ($addressType !== null || $addressId !== null) {
            if ($addressType === null || $addressId === null) {
                throw new InvalidArgumentException('The [address_type] and [address_id] fields must be provided together.');
            }

            $record = EventAddressRegistry::resolveRecord($addressType, $addressId);

            if (! $record instanceof EventAddressable && $strict) {
                throw new InvalidArgumentException(sprintf(
                    'The [%s] address model must implement %s.',
                    $addressType,
                    EventAddressable::class,
                ));
            }

            return $record;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $occurrence
     */
    private function resolveSubLocation(mixed $subLocation, array $occurrence): ?EventSubLocation
    {
        $candidate = $subLocation
            ?? $occurrence['sub_location']
            ?? $occurrence['subLocation']
            ?? null;

        if ($candidate instanceof EventSubLocation) {
            return $candidate;
        }

        if (is_array($candidate)) {
            $subLocationId = $this->stringOrNull($candidate['sub_location_id'] ?? $candidate['id'] ?? null);

            if ($subLocationId !== null) {
                $record = EventSubLocation::query()
                    ->forOwner()
                    ->find($subLocationId);

                if ($record instanceof EventSubLocation) {
                    return $record;
                }

                throw new InvalidArgumentException('The selected sub-location does not exist in the current owner scope.');
            }

            $slug = $this->requireString($candidate, 'slug');

            return EventSubLocation::query()
                ->forOwner()
                ->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $this->stringOrNull($candidate['name'] ?? null)
                            ?? Str::headline(str_replace(['-', '_'], ' ', $slug)),
                        'description' => $this->stringOrNull($candidate['description'] ?? null),
                        'order_column' => $this->intOrNull($candidate['order_column'] ?? null),
                    ],
                );
        }

        if (! is_scalar($candidate)) {
            return null;
        }

        $slug = $this->stringOrNull($candidate);

        if ($slug === null) {
            return null;
        }

        return EventSubLocation::query()
            ->forOwner()
            ->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => Str::headline(str_replace(['-', '_'], ' ', $slug)),
                ],
            );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildScheduleLocationPayload(?Model $address, ?EventSubLocation $subLocation): ?array
    {
        if ($address instanceof EventAddressable) {
            $addressData = app(EventAddressResolver::class)->data($address);

            $payload = [
                'address_type' => $address->getMorphClass(),
                'address_id' => (string) $address->getKey(),
                'address_label' => $addressData?->label,
                'address_lines' => $addressData?->lines ?? [],
                'address_latitude' => $addressData?->latitude,
                'address_longitude' => $addressData?->longitude,
                'address_country' => $addressData?->country,
                'address_timezone' => $addressData?->timezone,
                'timezone' => $addressData?->timezone,
            ];

            if ($subLocation instanceof EventSubLocation) {
                $payload['sub_location'] = [
                    'id' => (string) $subLocation->getKey(),
                    'name' => $subLocation->name,
                    'slug' => $subLocation->slug,
                    'description' => $subLocation->description,
                    'order_column' => $subLocation->order_column,
                ];
            }

            return $payload;
        }

        if ($subLocation instanceof EventSubLocation) {
            return [
                'sub_location' => [
                    'id' => (string) $subLocation->getKey(),
                    'name' => $subLocation->name,
                    'slug' => $subLocation->slug,
                    'description' => $subLocation->description,
                    'order_column' => $subLocation->order_column,
                ],
            ];
        }

        return null;
    }

    private function addressTimezone(?Model $address): ?string
    {
        if (! $address instanceof EventAddressable) {
            return null;
        }

        return app(EventAddressResolver::class)->timezone($address);
    }

    /**
     * @param  array<string, mixed>  $occurrence
     * @return array<string, mixed>|null
     */
    private function addressPayloadFromOccurrence(array $occurrence): ?array
    {
        $addressType = $this->stringOrNull($occurrence['address_type'] ?? null);
        $addressId = $this->stringOrNull($occurrence['address_id'] ?? null);

        if ($addressType === null && $addressId === null) {
            return null;
        }

        return [
            'address_type' => $addressType,
            'address_id' => $addressId,
        ];
    }

    /**
     * @param  array<string, mixed>  $occurrence
     * @param  array<string, mixed>  $schedule
     */
    private function ensureOccurrence(Event $event, ?Model $address, ?EventSubLocation $subLocation, array $occurrence, array $schedule): Occurrence
    {
        $timezone = $this->stringOrNull($schedule['timezone'] ?? null)
            ?? $this->stringOrNull($occurrence['timezone'] ?? null)
            ?? $event->default_timezone
            ?? $this->addressTimezone($address)
            ?? 'UTC';

        $startsAt = $this->dateTimeOrNull($schedule['starts_at'] ?? $occurrence['starts_at'] ?? null, $timezone);

        if ($startsAt === null) {
            $scheduleMode = $this->stringOrNull($schedule['schedule_mode'] ?? $occurrence['schedule_mode'] ?? null);

            if ($scheduleMode === null || $scheduleMode === 'manual') {
                throw new InvalidArgumentException('The [starts_at] field is required.');
            }

            throw new InvalidArgumentException(sprintf(
                'The [starts_at] field could not be resolved by the schedule resolver for mode [%s]. '
                . 'Bind a class implementing %s in [events.schedule.resolver] or supply an absolute [starts_at].',
                $scheduleMode,
                EventScheduleResolver::class,
            ));
        }

        $endsAt = $this->dateTimeOrNull($schedule['ends_at'] ?? $occurrence['ends_at'] ?? null, $timezone)
            ?? ($event->default_duration_minutes !== null ? $startsAt->addMinutes($event->default_duration_minutes) : null);

        return Occurrence::query()
            ->forOwner()
            ->updateOrCreate(
                [
                    'event_id' => $event->id,
                    'starts_at' => $startsAt,
                ],
                [
                    'address_type' => $address?->getMorphClass(),
                    'address_id' => $address !== null ? (string) $address->getKey() : null,
                    'sub_location_id' => $subLocation !== null ? (string) $subLocation->getKey() : null,
                    'product_id' => $this->stringOrNull($occurrence['product_id'] ?? null),
                    'variant_id' => $this->stringOrNull($occurrence['variant_id'] ?? null),
                    'name' => $this->stringOrNull($occurrence['name'] ?? null),
                    'status' => $this->resolveOccurrenceStatus($occurrence['status'] ?? null),
                    'participation_mode' => $this->resolveParticipationMode($occurrence['participation_mode'] ?? null),
                    'capacity' => $this->intOrNull($occurrence['capacity'] ?? null),
                    'ends_at' => $endsAt,
                    'timezone' => $timezone,
                    'schedule_mode' => $this->stringOrNull($schedule['schedule_mode'] ?? $occurrence['schedule_mode'] ?? null),
                    'schedule_reference_key' => $this->stringOrNull($schedule['schedule_reference_key'] ?? $occurrence['schedule_reference_key'] ?? null),
                    'schedule_reference_payload' => $this->arrayOrNull($schedule['schedule_reference_payload'] ?? $occurrence['schedule_reference_payload'] ?? null),
                    'schedule_label' => $this->stringOrNull($schedule['schedule_label'] ?? $occurrence['schedule_label'] ?? null),
                    'registration_opens_at' => $this->dateTimeOrNull($occurrence['registration_opens_at'] ?? null, $timezone),
                    'registration_closes_at' => $this->dateTimeOrNull($occurrence['registration_closes_at'] ?? null, $timezone),
                    'check_in_opens_at' => $this->dateTimeOrNull($occurrence['check_in_opens_at'] ?? null, $timezone),
                    'check_in_closes_at' => $this->dateTimeOrNull($occurrence['check_in_closes_at'] ?? null, $timezone),
                    'metadata' => $this->arrayOrNull($occurrence['metadata'] ?? null),
                ],
            );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function resolveParentEventId(array $event, EventSeries $series, bool $isParentPayload): ?string
    {
        $parentEvent = $event['parent_event'] ?? null;

        if (is_array($parentEvent)) {
            return (string) $this->ensureEvent($parentEvent, $series, true)->getKey();
        }

        if ($parentEvent instanceof Model) {
            return (string) $parentEvent->getKey();
        }

        $parentEventId = $this->stringOrNull($event['parent_event_id'] ?? null);

        if ($parentEventId !== null) {
            return $parentEventId;
        }

        if ($isParentPayload) {
            return null;
        }

        return null;
    }

    private function resolveEventStructure(mixed $value, bool $hasParentEvent, bool $isParentPayload): EventStructure
    {
        if ($value instanceof EventStructure) {
            return $value;
        }

        if (is_string($value) && EventStructure::tryFrom($value) instanceof EventStructure) {
            return EventStructure::from($value);
        }

        if ($isParentPayload) {
            return EventStructure::Program;
        }

        if ($hasParentEvent) {
            return EventStructure::Session;
        }

        $configuredStructure = config('events.defaults.event_structure', EventStructure::Standalone->value);

        if (is_string($configuredStructure) && EventStructure::tryFrom($configuredStructure) instanceof EventStructure) {
            return EventStructure::from($configuredStructure);
        }

        return EventStructure::Standalone;
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
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }

        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, $timezone)->utc();
    }

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    private function syncPeople(Event $event, array $eventPayload): void
    {
        $people = $eventPayload['people'] ?? null;

        if (! is_array($people)) {
            return;
        }

        EventPerson::query()
            ->where('event_id', $event->id)
            ->delete();

        foreach (array_values($people) as $index => $personPayload) {
            if (! is_array($personPayload)) {
                continue;
            }

            $person = $personPayload['person'] ?? null;
            $roleLabel = $this->stringOrNull($personPayload['role_label'] ?? $personPayload['role'] ?? null);
            $roleKey = $this->stringOrNull($personPayload['role_key'] ?? null) ?? ($roleLabel !== null ? Str::slug($roleLabel) : null);
            $visibility = $this->stringOrNull($personPayload['visibility'] ?? null)
                ?? ($this->booleanOrNull($personPayload['is_public'] ?? null) !== null
                    ? ($personPayload['is_public'] ? 'public' : 'private')
                    : 'public'
                );

            EventPerson::query()->create([
                'event_id' => $event->id,
                'person_type' => $this->stringOrNull($personPayload['person_type'] ?? null)
                    ?? ($person instanceof Model ? $person->getMorphClass() : null),
                'person_id' => $this->stringOrNull($personPayload['person_id'] ?? null)
                    ?? ($person instanceof Model ? (string) $person->getKey() : null),
                'display_name' => $this->resolveDisplayName($personPayload, $person),
                'role' => $roleLabel ?? $roleKey,
                'role_key' => $roleKey,
                'role_label' => $roleLabel,
                'visibility' => $visibility,
                'biography' => $this->stringOrNull($personPayload['biography'] ?? null),
                'order_column' => $this->intOrNull($personPayload['order_column'] ?? null) ?? $index + 1,
                'metadata' => $this->mergeMetadata($personPayload['metadata'] ?? null, [
                    'role_key' => $roleKey,
                    'role_label' => $roleLabel,
                    'visibility' => $visibility,
                ]),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>|null
     */
    private function mergeMetadata(mixed $metadata, array $extra): ?array
    {
        $base = $this->arrayOrNull($metadata) ?? [];

        $filteredExtra = array_filter(
            $extra,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        $merged = array_merge($base, $filteredExtra);

        return $merged !== [] ? $merged : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveDisplayName(array $payload, mixed $person): ?string
    {
        $displayName = $this->stringOrNull($payload['display_name'] ?? null);

        if ($displayName !== null) {
            return $displayName;
        }

        if (! $person instanceof Model) {
            return null;
        }

        foreach (['display_name', 'name', 'title', 'label'] as $attribute) {
            $candidate = $this->stringOrNull($person->getAttribute($attribute));

            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function booleanOrNull(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $filtered = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            return $filtered;
        }

        return null;
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
