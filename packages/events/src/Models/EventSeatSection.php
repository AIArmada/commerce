<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventSeatSectionFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_seat_map_id
 * @property string $name
 * @property string|null $code
 * @property string $section_type
 * @property string|null $seat_category
 * @property int $capacity
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventSeatSection extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_seat_map_id',
        'name', 'code', 'section_type', 'seat_category',
        'capacity', 'sort_order',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_seat_sections', 'event_seat_sections');
    }

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EventSeatMap, $this>
     */
    public function map(): BelongsTo
    {
        return $this->belongsTo(EventSeatMap::class, 'event_seat_map_id');
    }

    /**
     * @return HasMany<EventSeat, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(EventSeat::class, 'event_seat_section_id');
    }

    /**
     * @return HasMany<EventSeatHold, $this>
     */
    public function holds(): HasMany
    {
        return $this->hasMany(EventSeatHold::class, 'event_seat_section_id');
    }

    /**
     * @return HasMany<EventSeatAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(EventSeatAllocation::class, 'event_seat_section_id');
    }

    /**
     * @return HasMany<EventTicketTypeSeatingOption, $this>
     */
    public function seatingOptions(): HasMany
    {
        return $this->hasMany(EventTicketTypeSeatingOption::class, 'event_seat_section_id');
    }

    protected static function newFactory(): EventSeatSectionFactory
    {
        return EventSeatSectionFactory::new();
    }
}
