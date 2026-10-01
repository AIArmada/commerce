<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Addressing\Traits\HasAddresses;
use AIArmada\Events\Database\Factories\VenueFacilityFactory;
use AIArmada\Events\Enums\FacilityAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string|null $venue_id
 * @property string|null $venue_space_id
 * @property string $facility_type_id
 * @property FacilityAvailability $availability
 * @property int|null $quantity Number of units (parking bays, rooms, counters).
 * @property int|null $capacity Person capacity served by this facility value.
 * @property bool $is_free
 * @property int|null $fee_amount Minor units plus currency when not free.
 * @property string|null $currency
 * @property string|null $location_label
 * @property string|null $notes
 * @property string $visibility
 * @property CarbonImmutable|null $verified_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Venue|null $venue
 * @property-read VenueSpace|null $venueSpace
 * @property-read FacilityType $facilityType
 */
final class VenueFacility extends Model
{
    use HasAddresses;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'venue_id', 'venue_space_id', 'facility_type_id',
        'availability', 'quantity', 'capacity',
        'is_free', 'fee_amount', 'currency',
        'location_label', 'notes',
        'visibility', 'verified_at',
        'metadata',
    ];

    protected $attributes = [
        'availability' => 'available',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.venue_facilities', 'venue_facilities');
    }

    protected function casts(): array
    {
        return [
            'availability' => FacilityAvailability::class,
            'quantity' => 'integer',
            'capacity' => 'integer',
            'is_free' => 'boolean',
            'fee_amount' => 'integer',
            'verified_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (VenueFacility $facility): void {
            $facility->applyAvailabilityDefault();
            $facility->guardCatalogReference();
            $facility->guardPlaceReference();
        });
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
     * @return BelongsTo<FacilityType, $this>
     */
    public function facilityType(): BelongsTo
    {
        return $this->belongsTo(FacilityType::class);
    }

    /**
     * @param  Builder<VenueFacility>  $query
     * @return Builder<VenueFacility>
     */
    public function scopeForVenue(Builder $query, Venue | string $venue): Builder
    {
        return $query->where('venue_id', self::resolveScopeId($venue, 'venue_id'));
    }

    /**
     * @param  Builder<VenueFacility>  $query
     * @return Builder<VenueFacility>
     */
    public function scopeForSpace(Builder $query, VenueSpace | string $space): Builder
    {
        return $query->where('venue_space_id', self::resolveScopeId($space, 'venue_space_id'));
    }

    /**
     * Venue-wide rows: bound to a venue with no space.
     *
     * @param  Builder<VenueFacility>  $query
     * @return Builder<VenueFacility>
     */
    public function scopeVenueWide(Builder $query): Builder
    {
        return $query->whereNotNull('venue_id')->whereNull('venue_space_id');
    }

    /**
     * @param  Builder<VenueFacility>  $query
     * @return Builder<VenueFacility>
     */
    public function scopeForFacilityType(Builder $query, FacilityType | string $type): Builder
    {
        return $query->where('facility_type_id', self::resolveScopeId($type, 'facility_type_id'));
    }

    /**
     * @param  Builder<VenueFacility>  $query
     * @return Builder<VenueFacility>
     */
    public function scopeWhereAvailability(Builder $query, FacilityAvailability | string $availability): Builder
    {
        $value = $availability instanceof FacilityAvailability ? $availability->value : $availability;

        return $query->where('availability', $value);
    }

    /**
     * @param  Builder<VenueFacility>  $query
     * @return Builder<VenueFacility>
     */
    public function scopeWhereTypeCode(Builder $query, string $code): Builder
    {
        return $query->whereHas('facilityType', fn (Builder $related): Builder => $related->where('code', $code));
    }

    private function applyAvailabilityDefault(): void
    {
        if (($this->getAttributes()['availability'] ?? null) === null) {
            $this->setAttribute('availability', FacilityAvailability::Available);
        }
    }

    private function guardCatalogReference(): void
    {
        $typeId = $this->getAttribute('facility_type_id');

        if (! is_string($typeId) || $typeId === '') {
            throw new InvalidArgumentException('A facility type is required to save a venue facility.');
        }

        if (! Str::isUuid($typeId)) {
            throw new InvalidArgumentException("Facility type [{$typeId}] is invalid.");
        }

        if (! FacilityType::query()->whereKey($typeId)->exists()) {
            throw new InvalidArgumentException("Facility type [{$typeId}] does not exist.");
        }
    }

    private function guardPlaceReference(): void
    {
        $spaceId = $this->normalizePlaceId($this->getAttribute('venue_space_id'), 'venue_space_id');
        $venueId = $this->normalizePlaceId($this->getAttribute('venue_id'), 'venue_id');

        $this->setAttribute('venue_space_id', $spaceId);
        $this->setAttribute('venue_id', $venueId);

        if ($spaceId !== null) {
            $space = VenueSpace::query()->select(['id', 'venue_id'])->whereKey($spaceId)->first();

            if (! $space instanceof VenueSpace) {
                throw new InvalidArgumentException("Venue space [{$spaceId}] does not exist.");
            }

            $spaceVenueId = $space->getAttribute('venue_id');
            $spaceVenueId = is_string($spaceVenueId) && $spaceVenueId !== '' ? $spaceVenueId : null;

            if ($venueId === null) {
                $this->setAttribute('venue_id', $spaceVenueId);
                $venueId = $spaceVenueId;
            }

            if ($venueId !== $spaceVenueId) {
                throw new InvalidArgumentException("Venue [{$venueId}] does not own venue space [{$spaceId}].");
            }

            if ($venueId === null) {
                return;
            }
        }

        if ($venueId === null) {
            throw new InvalidArgumentException('A venue or venue space is required to save a venue facility.');
        }

        if (! Venue::query()->whereKey($venueId)->exists()) {
            throw new InvalidArgumentException("Venue [{$venueId}] does not exist.");
        }
    }

    /**
     * Only null is a legitimate empty place id. Non-string values and empty
     * strings are rejected instead of being silently treated as null, so a
     * malformed inbound id can never bypass the existence checks or persist
     * unchecked.
     */
    private function normalizePlaceId(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || $value === '' || ! Str::isUuid($value)) {
            throw new InvalidArgumentException("A {$field} must be a valid UUID string or null.");
        }

        return $value;
    }

    private static function resolveScopeId(Venue | VenueSpace | FacilityType | string $target, string $field): string
    {
        $id = is_string($target) ? $target : $target->getKey();

        if (! is_string($id) || $id === '' || ! Str::isUuid($id)) {
            throw new InvalidArgumentException("A {$field} must be a valid UUID string to filter venue facilities.");
        }

        return $id;
    }

    protected static function newFactory(): VenueFacilityFactory
    {
        return VenueFacilityFactory::new();
    }
}
