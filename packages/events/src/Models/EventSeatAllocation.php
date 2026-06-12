<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventSeatAllocationFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $event_pass_id
 * @property string|null $event_registration_participant_id
 * @property string|null $event_seat_section_id
 * @property string|null $event_seat_id
 * @property string $allocation_type
 * @property string $status
 * @property CarbonImmutable|null $allocated_at
 * @property CarbonImmutable|null $released_at
 * @property CarbonImmutable|null $revoked_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventSeatAllocation extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'event_pass_id', 'event_registration_participant_id',
        'event_seat_section_id', 'event_seat_id',
        'allocation_type', 'status',
        'allocated_at', 'released_at', 'revoked_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_seat_allocations', 'event_seat_allocations');
    }

    protected function casts(): array
    {
        return [
            'allocated_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
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
     * @return BelongsTo<EventPass, $this>
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(EventPass::class, 'event_pass_id');
    }

    /**
     * @return BelongsTo<EventRegistrationParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationParticipant::class, 'event_registration_participant_id');
    }

    /**
     * @return BelongsTo<EventSeatSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(EventSeatSection::class, 'event_seat_section_id');
    }

    /**
     * @return BelongsTo<EventSeat, $this>
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(EventSeat::class, 'event_seat_id');
    }

    protected static function newFactory(): EventSeatAllocationFactory
    {
        return EventSeatAllocationFactory::new();
    }
}
