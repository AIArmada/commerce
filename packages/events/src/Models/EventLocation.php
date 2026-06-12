<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventLocationFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string $location_role
 * @property string|null $locationable_type
 * @property string|null $locationable_id
 * @property string|null $venue_id
 * @property string|null $venue_space_id
 * @property string|null $venue_space_type_id
 * @property string|null $label
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $district
 * @property string|null $state
 * @property string|null $postcode
 * @property string|null $country
 * @property string|null $level
 * @property string|null $unit_no
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $google_place_id
 * @property string|null $google_maps_url
 * @property string|null $waze_url
 * @property string|null $map_url
 * @property string|null $directions
 * @property array|null $address_snapshot
 * @property CarbonImmutable|null $geocoded_at
 * @property string|null $geocoding_source
 * @property string $visibility
 * @property string $status
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 * @property-read Model|Eloquent $locationable
 * @property-read Venue|null $venue
 * @property-read VenueSpace|null $venueSpace
 * @property-read VenueSpaceType|null $venueSpaceType
 * @property-read Collection<int, EventFacility> $facilities
 */
final class EventLocation extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'location_role',
        'locationable_type', 'locationable_id',
        'venue_id', 'venue_space_id', 'venue_space_type_id',
        'label',
        'address_line_1', 'address_line_2',
        'city', 'district', 'state', 'postcode', 'country',
        'level', 'unit_no',
        'latitude', 'longitude',
        'google_place_id', 'google_maps_url', 'waze_url', 'map_url', 'directions',
        'address_snapshot',
        'geocoded_at', 'geocoding_source',
        'visibility', 'status', 'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_locations', 'event_locations');
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'address_snapshot' => 'array',
            'geocoded_at' => 'immutable_datetime',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<EventSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function locationable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<VenueSpace, $this>
     */
    public function venueSpace(): BelongsTo
    {
        return $this->belongsTo(VenueSpace::class);
    }

    /**
     * @return BelongsTo<VenueSpaceType, $this>
     */
    public function venueSpaceType(): BelongsTo
    {
        return $this->belongsTo(VenueSpaceType::class);
    }

    /**
     * @return HasMany<EventFacility, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(EventFacility::class, 'event_location_id');
    }

    protected static function newFactory(): EventLocationFactory
    {
        return EventLocationFactory::new();
    }
}
