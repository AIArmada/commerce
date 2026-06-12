<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventItineraryItemFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_itinerary_id
 * @property string|null $event_id
 * @property string|null $event_occurrence_id
 * @property string $item_type
 * @property string|null $event_session_id
 * @property string $title
 * @property string|null $description
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property string|null $venue_id
 * @property string|null $event_location_id
 * @property string|null $location_label
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 */
final class EventItineraryItem extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_itinerary_id',
        'event_id', 'event_occurrence_id',
        'item_type', 'event_session_id',
        'title', 'description',
        'starts_at', 'ends_at',
        'venue_id', 'event_location_id', 'location_label',
        'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_itinerary_items', 'event_itinerary_items');
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<EventItinerary, $this>
     */
    public function itinerary(): BelongsTo
    {
        return $this->belongsTo(EventItinerary::class, 'event_itinerary_id');
    }

    /**
     * @return BelongsTo<EventSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    /**
     * @return BelongsTo<EventLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(EventLocation::class, 'event_location_id');
    }

    protected static function newFactory(): EventItineraryItemFactory
    {
        return EventItineraryItemFactory::new();
    }
}
