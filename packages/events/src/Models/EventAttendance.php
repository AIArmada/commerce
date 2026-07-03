<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventAttendanceFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use AIArmada\Ticketing\Models\Pass;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $event_registration_id
 * @property string|null $event_registration_participant_id
 * @property string|null $pass_id
 * @property string|null $attendee_type
 * @property string|null $attendee_id
 * @property string $attendance_type
 * @property CarbonImmutable|null $checked_in_at
 * @property CarbonImmutable|null $checked_out_at
 * @property string|null $check_in_source
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $corrected_at
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EventAttendanceLog> $logs
 */
final class EventAttendance extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'event_registration_id', 'event_registration_participant_id',
        'pass_id',
        'attendee_type', 'attendee_id',
        'attendance_type',
        'checked_in_at', 'checked_out_at', 'check_in_source',
        'cancelled_at', 'corrected_at',
        'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_attendances', 'event_attendances');
    }

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'immutable_datetime',
            'checked_out_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'corrected_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
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
     * @return BelongsTo<Pass, $this>
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class, 'pass_id');
    }

    /**
     * @return BelongsTo<EventRegistrationParticipant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationParticipant::class, 'event_registration_participant_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attendee(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<EventAttendanceLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(EventAttendanceLog::class);
    }

    protected static function newFactory(): EventAttendanceFactory
    {
        return EventAttendanceFactory::new();
    }
}
