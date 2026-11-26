<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Venues\Models;

use AIArmada\Addressing\Features\AddressLifecycle\Traits\HasAddresses;
use AIArmada\Events\Database\Factories\EventLocationFactory;
use AIArmada\Events\Features\Venues\Actions\DeleteEventLocationDependents;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Events\Platform\ScopesByEventOwner;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
 * @property string|null $space_name_snapshot
 * @property string|null $label
 * @property string|null $line1
 * @property string|null $line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postcode
 * @property string|null $country_code
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
 * @property-read Model $event
 * @property-read Model|null $occurrence
 * @property-read Model|null $session
 * @property-read Model|Eloquent $locationable
 * @property-read Model|null $venue
 * @property-read Model|null $venueSpace
 * @property-read VenueSpaceType|null $venueSpaceType
 * @property-read Collection<int, EventFacility> $facilities
 */
final class EventLocation extends Model
{
    use HasAddresses;
    use HasFactory;
    use HasUuids;
    use ScopesByEventOwner;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'location_role',
        'locationable_type', 'locationable_id',
        'venue_id', 'venue_space_id', 'venue_space_type_id', 'space_name_snapshot',
        'label',
        'level', 'unit_no',
        'visibility', 'status', 'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('events.database.tables.event_locations', 'event_locations');
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

    protected static function booted(): void
    {
        static::saving(function (EventLocation $location): void {
            if (! $location->isDirty('venue_space_id') && filled($location->space_name_snapshot)) {
                return;
            }

            if (blank($location->venue_space_id)) {
                if ($location->isDirty('venue_space_id')) {
                    $location->space_name_snapshot = null;
                }

                return;
            }

            $spaceName = $location->venueSpace()->value('name');

            if (is_string($spaceName)) {
                $location->space_name_snapshot = $spaceName;
            }
        });

        static::deleting(function (EventLocation $location): void {
            app(DeleteEventLocationDependents::class)->handle($location);
        });
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::eventClass());
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::occurrenceClass(), 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::sessionClass(), 'event_session_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function locationable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::venueClass());
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function venueSpace(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::venueSpaceClass());
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
