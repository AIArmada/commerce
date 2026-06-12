<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\VenueFacilityFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $venue_id
 * @property string|null $venue_space_id
 * @property string $facility_type_id
 * @property string|null $availability
 * @property int|null $quantity
 * @property int|null $capacity
 * @property bool $is_free
 * @property int|null $fee_amount
 * @property string|null $currency
 * @property string|null $location_label
 * @property string|null $notes
 * @property string $visibility
 * @property CarbonImmutable|null $verified_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Venue $venue
 * @property-read VenueSpace|null $venueSpace
 * @property-read FacilityType $facilityType
 */
final class VenueFacility extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'venue_id', 'venue_space_id', 'facility_type_id',
        'availability', 'quantity', 'capacity',
        'is_free', 'fee_amount', 'currency',
        'location_label', 'notes',
        'visibility', 'verified_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.venue_facilities', 'venue_facilities');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'capacity' => 'integer',
            'is_free' => 'boolean',
            'fee_amount' => 'integer',
            'verified_at' => 'immutable_datetime',
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

    protected static function newFactory(): VenueFacilityFactory
    {
        return VenueFacilityFactory::new();
    }
}
