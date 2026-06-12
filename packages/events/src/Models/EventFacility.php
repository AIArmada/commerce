<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventFacilityFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string $facility_type_id
 * @property string|null $event_location_id
 * @property string|null $availability
 * @property int|null $quantity
 * @property int|null $capacity
 * @property bool $is_free
 * @property int|null $fee_amount
 * @property string|null $currency
 * @property string|null $location_label
 * @property string|null $notes
 * @property string $visibility
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 * @property-read FacilityType $facilityType
 * @property-read EventLocation|null $eventLocation
 */
final class EventFacility extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'facility_type_id', 'event_location_id',
        'availability', 'quantity', 'capacity',
        'is_free', 'fee_amount', 'currency',
        'location_label', 'notes',
        'visibility',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_facilities', 'event_facilities');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'capacity' => 'integer',
            'is_free' => 'boolean',
            'fee_amount' => 'integer',
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
     * @return BelongsTo<FacilityType, $this>
     */
    public function facilityType(): BelongsTo
    {
        return $this->belongsTo(FacilityType::class);
    }

    /**
     * @return BelongsTo<EventLocation, $this>
     */
    public function eventLocation(): BelongsTo
    {
        return $this->belongsTo(EventLocation::class);
    }

    protected static function newFactory(): EventFacilityFactory
    {
        return EventFacilityFactory::new();
    }
}
