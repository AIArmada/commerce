<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Database\Factories\EventItineraryFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $name
 * @property string $itinerary_type
 * @property string $visibility
 * @property string $status
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventItinerary extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesEventUuid;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'event_id', 'event_occurrence_id',
        'owner_type', 'owner_id',
        'name', 'itinerary_type',
        'visibility', 'status',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_itineraries', 'event_itineraries');
    }

    protected function casts(): array
    {
        return [
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
     * @return HasMany<EventItineraryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EventItineraryItem::class);
    }

    protected static function newFactory(): EventItineraryFactory
    {
        return EventItineraryFactory::new();
    }
}
