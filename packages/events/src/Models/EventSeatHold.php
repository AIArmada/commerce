<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventSeatHoldFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $event_seat_id
 * @property string|null $event_seat_section_id
 * @property string|null $holder_type
 * @property string|null $holder_id
 * @property string|null $event_registration_id
 * @property int $quantity
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $released_at
 * @property CarbonImmutable|null $converted_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventSeatHold extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'event_seat_id', 'event_seat_section_id',
        'holder_type', 'holder_id', 'event_registration_id',
        'quantity',
        'expires_at', 'released_at', 'converted_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_seat_holds', 'event_seat_holds');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'converted_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
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
     * @return BelongsTo<EventSeat, $this>
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(EventSeat::class, 'event_seat_id');
    }

    /**
     * @return BelongsTo<EventSeatSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(EventSeatSection::class, 'event_seat_section_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    protected static function newFactory(): EventSeatHoldFactory
    {
        return EventSeatHoldFactory::new();
    }
}
