<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Attendance\Models;

use AIArmada\Events\Database\Factories\EventAttendanceFactory;
use AIArmada\Events\Features\Attendance\Actions\DeleteAttendanceDependents;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\Events\Platform\ScopesByEventOwner;
use AIArmada\Ticketing\Models\Pass;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
 * @property string|null $verified_by_user_id
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $corrected_at
 * @property string|null $notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EventAttendanceLog> $logs
 */
class EventAttendance extends Model
{
    use HasFactory;
    use HasUuids;
    use ScopesByEventOwner;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'event_registration_id', 'event_registration_participant_id',
        'pass_id',
        'attendee_type', 'attendee_id',
        'attendance_type',
        'checked_in_at', 'checked_out_at', 'check_in_source',
        'verified_by_user_id',
        'cancelled_at', 'corrected_at',
        'notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('events.database.tables.event_attendances', 'event_attendances');
    }

    protected static function booted(): void
    {
        static::deleting(function (EventAttendance $attendance): void {
            app(DeleteAttendanceDependents::class)->handle((string) $attendance->getKey());
        });
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
     * @return BelongsTo<Model, $this>
     */
    public function registration(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::registrationClass();

        return $this->belongsTo($modelClass, 'event_registration_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function event(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::eventClass();

        return $this->belongsTo($modelClass);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function occurrence(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::occurrenceClass();

        return $this->belongsTo($modelClass, 'event_occurrence_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function session(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::sessionClass();

        return $this->belongsTo($modelClass, 'event_session_id');
    }

    /**
     * @return BelongsTo<Pass, $this>
     */
    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class, 'pass_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function participant(): BelongsTo
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = ModelResolver::registrationParticipantClass();

        return $this->belongsTo($modelClass, 'event_registration_participant_id');
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
