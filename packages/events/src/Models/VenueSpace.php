<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Addressing\Traits\HasAddresses;
use AIArmada\Events\Database\Factories\VenueSpaceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string|null $venue_id
 * @property string $name
 * @property string|null $code
 * @property string|null $space_type
 * @property string|null $level
 * @property string|null $unit_no
 * @property string|null $block
 * @property string|null $wing
 * @property int|null $capacity Default/suggested capacity of this shared space definition. Actual per-institution capacity lives app-side (in ilmu360, the institution_space pivot); both null means unknown and needs app policy.
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
 * @property-read Venue|null $venue Null for a shared standalone space template.
 * @property-read Collection<int, VenueFacility> $facilities
 * @property-read Collection<int, EventLocation> $eventLocations
 */
class VenueSpace extends Model
{
    use HasAddresses;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'venue_id',
        'name', 'slug', 'code', 'space_type',
        'level', 'unit_no', 'block', 'wing',
        'capacity',
        'status', 'visibility',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.venue_spaces', 'venue_spaces');
    }

    protected static function booted(): void
    {
        static::saving(function (VenueSpace $space): void {
            $space->guardVenueReference();
        });

        static::deleting(function (VenueSpace $space): void {
            VenueFacility::query()->where('venue_space_id', $space->getKey())->delete();
        });
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
     * Rows bound to this space only.
     *
     * For a venue-bound space these rows also carry the venue id, so they
     * appear in Venue::facilities too. For a standalone template
     * (venue_id null) the rows carry a null venue id and appear only here.
     *
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
        return $this->hasMany(EventLocation::class, 'venue_space_id');
    }

    /**
     * A persisted venue_id change would leave attached VenueFacility rows
     * pointing at the old venue, so reparenting is rejected while any
     * facility is attached. Remove the space facilities first, then move the
     * space. Spaces without facilities move freely in either direction.
     */
    private function guardVenueReference(): void
    {
        $venueId = $this->getAttribute('venue_id');

        if ($venueId !== null && (! is_string($venueId) || $venueId === '' || ! Str::isUuid($venueId))) {
            throw new InvalidArgumentException('A venue id must be a valid UUID string or null.');
        }

        if ($venueId !== null && ! Venue::query()->whereKey($venueId)->exists()) {
            throw new InvalidArgumentException("Venue [{$venueId}] does not exist.");
        }

        if ($this->exists && $this->isDirty('venue_id') && $this->facilities()->exists()) {
            throw new InvalidArgumentException(
                'VenueSpace venue cannot be changed while facilities are attached. Remove the space facilities first.'
            );
        }
    }

    protected static function newFactory(): VenueSpaceFactory
    {
        return VenueSpaceFactory::new();
    }
}
