<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventPassFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use AIArmada\Seating\Models\SeatAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $event_registration_id
 * @property string|null $event_registration_participant_id
 * @property string|null $event_registration_item_id
 * @property string|null $event_ticket_type_id
 * @property string $pass_no
 * @property string|null $qr_code
 * @property string|null $barcode
 * @property string $status
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $voided_at
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable|null $expired_at
 * @property string|null $status_reason
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventPass extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'event_registration_id', 'event_registration_participant_id',
        'event_registration_item_id', 'event_ticket_type_id',
        'pass_no', 'qr_code', 'barcode',
        'status',
        'issued_at', 'activated_at', 'cancelled_at', 'revoked_at',
        'voided_at', 'used_at', 'expired_at',
        'status_reason',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_passes', 'event_passes');
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
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    /**
     * @return BelongsTo<EventTicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(EventTicketType::class, 'event_ticket_type_id');
    }

    /**
     * @return BelongsTo<EventRegistrationParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationParticipant::class, 'event_registration_participant_id');
    }

    /**
     * @return BelongsTo<EventRegistrationItem, $this>
     */
    public function registrationItem(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationItem::class, 'event_registration_item_id');
    }

    /**
     * @return MorphMany<SeatAllocation, $this>
     */
    public function seatAllocations(): MorphMany
    {
        return $this->morphMany(SeatAllocation::class, 'allocated_to');
    }

    /**
     * @return HasMany<EventAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class, 'event_pass_id');
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): EventPassFactory
    {
        return EventPassFactory::new();
    }
}
