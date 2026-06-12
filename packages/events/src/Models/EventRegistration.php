<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventRegistrationFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $registrant_type
 * @property string|null $registrant_id
 * @property string $registration_no
 * @property string $registration_type
 * @property string $status
 * @property string $source
 * @property int $total_participants
 * @property int $total_amount
 * @property string $currency
 * @property string|null $external_order_id
 * @property string|null $external_order_type
 * @property string|null $payment_status
 * @property CarbonImmutable|null $registered_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $waitlisted_at
 * @property CarbonImmutable|null $expired_at
 * @property string|null $status_reason
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read Model|Eloquent $registrant
 * @property-read Collection<int, EventRegistrationParticipant> $participants
 * @property-read Collection<int, EventRegistrationAnswer> $answers
 * @property-read Collection<int, EventRegistrationItem> $items
 * @property-read Collection<int, EventPass> $passes
 * @property-read Collection<int, EventAttendance> $attendances
 */
final class EventRegistration extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'registrant_type', 'registrant_id',
        'registration_no', 'registration_type', 'status', 'source',
        'total_participants', 'total_amount', 'currency',
        'external_order_id', 'external_order_type', 'payment_status',
        'registered_at', 'approved_at', 'completed_at', 'cancelled_at', 'rejected_at',
        'waitlisted_at', 'expired_at',
        'status_reason', 'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_registrations', 'event_registrations');
    }

    protected static function booted(): void
    {
        static::creating(function (EventRegistration $registration): void {
            if (blank($registration->registration_no)) {
                $prefix = (string) config('events.codes.registration_prefix', 'REG');
                $length = max(6, (int) config('events.codes.registration_length', 10));

                $registration->registration_no = $prefix . '-' . mb_strtoupper(Str::random($length));
            }

            if ($registration->registered_at === null) {
                $registration->registered_at = CarbonImmutable::now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'total_participants' => 'integer',
            'total_amount' => 'decimal:2',
            'registered_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'waitlisted_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
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
     * @return MorphTo<Model, $this>
     */
    public function registrant(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<EventRegistrationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(EventRegistrationParticipant::class);
    }

    /**
     * @return HasMany<EventRegistrationAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(EventRegistrationAnswer::class);
    }

    /**
     * @return HasMany<EventRegistrationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EventRegistrationItem::class);
    }

    /**
     * @return HasMany<EventPass, $this>
     */
    public function passes(): HasMany
    {
        return $this->hasMany(EventPass::class);
    }

    /**
     * @return HasMany<EventAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(EventAttendance::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isWaitlisted(): bool
    {
        return $this->status === 'waitlisted';
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'approved_at' => $this->freshTimestamp(),
        ]);
    }

    protected static function newFactory(): EventRegistrationFactory
    {
        return EventRegistrationFactory::new();
    }

    /**
     * Get the effective scope for child records (participants, items, answers).
     * Returns [event_id, event_occurrence_id, event_session_id].
     *
     * @return array<string, string|null>
     */
    public function scopeFields(): array
    {
        return [
            'event_id' => $this->event_id,
            'event_occurrence_id' => $this->event_occurrence_id,
            'event_session_id' => $this->event_session_id,
        ];
    }

    public function promoteFromWaitlist(): void
    {
        $this->update([
            'status' => 'pending',
            'waitlisted_at' => null,
        ]);
    }
}
