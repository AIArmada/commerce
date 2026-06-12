<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventSeatFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_seat_section_id
 * @property string|null $row_label
 * @property string|null $seat_number
 * @property string $label
 * @property string $status
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventSeat extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_seat_section_id',
        'row_label', 'seat_number', 'label', 'status',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_seats', 'event_seats');
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EventSeatSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(EventSeatSection::class, 'event_seat_section_id');
    }

    /**
     * @return HasMany<EventSeatHold, $this>
     */
    public function holds(): HasMany
    {
        return $this->hasMany(EventSeatHold::class, 'event_seat_id');
    }

    /**
     * @return HasMany<EventSeatAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(EventSeatAllocation::class, 'event_seat_id');
    }

    protected static function newFactory(): EventSeatFactory
    {
        return EventSeatFactory::new();
    }
}
