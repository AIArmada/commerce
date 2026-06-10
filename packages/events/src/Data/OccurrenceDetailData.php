<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\EventAgenda;
use AIArmada\Events\Models\Occurrence;
use Spatie\LaravelData\Data;

final class OccurrenceDetailData extends Data
{
    /**
     * @param  array<string, mixed>  $references
     * @param  array<int, EventAgendaItemData>  $agendaItems
     * @param  array<int, string>  $addressLines
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $eventId,
        public readonly ?string $addressType = null,
        public readonly ?string $addressId = null,
        public readonly ?string $addressLabel = null,
        public readonly array $addressLines = [],
        public readonly ?string $addressLatitude = null,
        public readonly ?string $addressLongitude = null,
        public readonly ?string $addressCountry = null,
        public readonly ?string $addressTimezone = null,
        public readonly ?string $subLocationId = null,
        public readonly ?string $subLocationName = null,
        public readonly ?string $subLocationSlug = null,
        public readonly ?string $locationLabel = null,
        public readonly ?string $productId = null,
        public readonly ?string $variantId = null,
        public readonly ?string $name = null,
        public readonly string $status = 'draft',
        public readonly string $participationMode = 'registration_required',
        public readonly ?int $capacity = null,
        public readonly string $startsAt = '',
        public readonly ?string $endsAt = null,
        public readonly string $timezone = 'UTC',
        public readonly ?string $registrationOpensAt = null,
        public readonly ?string $registrationClosesAt = null,
        public readonly ?string $checkInOpensAt = null,
        public readonly ?string $checkInClosesAt = null,
        public readonly ?string $scheduleMode = null,
        public readonly ?string $scheduleReferenceKey = null,
        public readonly ?array $scheduleReferencePayload = null,
        public readonly ?string $scheduleLabel = null,
        public readonly string $registrationMode = 'free',
        public readonly string $duplicateStrategy = 'per_occurrence',
        public readonly bool $waitlistEnabled = false,
        public readonly bool $approvalRequired = false,
        public readonly array $references = [],
        public readonly array $agendaItems = [],
        public readonly ?array $metadata = null,
    ) {}

    public static function fromOccurrence(Occurrence $occurrence): self
    {
        $occurrence->loadMissing(['references', 'agendaItems', 'address', 'subLocation']);

        return new self(
            id: $occurrence->id,
            eventId: $occurrence->event_id,
            addressType: $occurrence->address_type,
            addressId: $occurrence->address_id,
            addressLabel: $occurrence->addressLabel(),
            addressLines: $occurrence->addressLines(),
            addressLatitude: $occurrence->addressLatitude(),
            addressLongitude: $occurrence->addressLongitude(),
            addressCountry: $occurrence->addressCountry(),
            addressTimezone: $occurrence->addressTimezone(),
            subLocationId: $occurrence->sub_location_id,
            subLocationName: $occurrence->subLocation?->name,
            subLocationSlug: $occurrence->subLocation?->slug,
            locationLabel: $occurrence->locationLabel(),
            productId: $occurrence->product_id,
            variantId: $occurrence->variant_id,
            name: $occurrence->name,
            status: $occurrence->status->value,
            participationMode: $occurrence->participation_mode->value,
            capacity: $occurrence->capacity,
            startsAt: $occurrence->starts_at->toISOString(),
            endsAt: $occurrence->ends_at?->toISOString(),
            timezone: $occurrence->timezone,
            registrationOpensAt: $occurrence->registration_opens_at?->toISOString(),
            registrationClosesAt: $occurrence->registration_closes_at?->toISOString(),
            checkInOpensAt: $occurrence->check_in_opens_at?->toISOString(),
            checkInClosesAt: $occurrence->check_in_closes_at?->toISOString(),
            scheduleMode: $occurrence->schedule_mode,
            scheduleReferenceKey: $occurrence->schedule_reference_key,
            scheduleReferencePayload: $occurrence->schedule_reference_payload,
            scheduleLabel: $occurrence->schedule_label,
            registrationMode: $occurrence->registration_mode,
            duplicateStrategy: $occurrence->duplicate_strategy,
            waitlistEnabled: $occurrence->waitlist_enabled,
            approvalRequired: $occurrence->approval_required,
            references: $occurrence->referenceMaterials(),
            agendaItems: $occurrence->agendaItems
                ->map(static fn (EventAgenda $agendaItem): EventAgendaItemData => EventAgendaItemData::fromAgendaItem($agendaItem))
                ->values()
                ->all(),
            metadata: $occurrence->metadata,
        );
    }
}
