<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Database\Factories\EventItineraryFactory;
use AIArmada\Events\Features\Catalog\Actions\DeleteItineraryDependents;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'event_id', 'event_occurrence_id',
        'name',
        'visibility', 'status',
        'metadata',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('events.database.tables.event_itineraries', 'event_itineraries');
    }

    protected static function booted(): void
    {
        static::deleting(function (EventItinerary $itinerary): void {
            app(DeleteItineraryDependents::class)->handle($itinerary);
        });
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
