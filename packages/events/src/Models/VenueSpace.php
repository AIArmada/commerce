<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\VenueSpaceFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $venue_id
 * @property string $name
 * @property string|null $code
 * @property string $space_type
 * @property string|null $level
 * @property string|null $unit_no
 * @property string|null $block
 * @property string|null $wing
 * @property int|null $capacity
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $google_maps_url
 * @property string|null $waze_url
 * @property string|null $map_url
 * @property string|null $directions
 * @property string $status
 * @property string $visibility
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Venue $venue
 * @property-read \Illuminate\Database\Eloquent\Collection<int, VenueFacility> $facilities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventLocation> $eventLocations
 */
final class VenueSpace extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'venue_id',
        'name', 'code', 'space_type',
        'level', 'unit_no', 'block', 'wing',
        'capacity',
        'latitude', 'longitude',
        'google_maps_url', 'waze_url', 'map_url', 'directions',
        'status', 'visibility',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.venue_spaces', 'venue_spaces');
    }

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return HasMany<VenueFacility, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(VenueFacility::class);
    }

    /**
     * @return HasMany<EventLocation, $this>
     */
    public function eventLocations(): HasMany
    {
        return $this->hasMany(EventLocation::class);
    }

    protected static function newFactory(): VenueSpaceFactory
    {
        return VenueSpaceFactory::new();
    }
}
