<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventRecurrenceRuleFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $recurrence_target_type
 * @property string|null $recurrence_target_id
 * @property string|null $code
 * @property string|null $name
 * @property string|null $description
 * @property string $recurrence_type
 * @property string $frequency
 * @property int $interval
 * @property array|null $days_of_week
 * @property array|null $days_of_month
 * @property array|null $months_of_year
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property int|null $max_occurrences
 * @property string $timezone
 * @property string|null $time_mode
 * @property string|null $starts_at_time
 * @property string|null $ends_at_time
 * @property string|null $anchor_type
 * @property string|null $anchor_code
 * @property string|null $relation
 * @property int|null $offset_minutes
 * @property string|null $rrule_text
 * @property string|null $human_readable_rule
 * @property string $status
 * @property string $visibility
 * @property CarbonImmutable|null $generated_until
 * @property CarbonImmutable|null $last_generated_at
 * @property CarbonImmutable|null $disabled_at
 * @property CarbonImmutable|null $archived_at
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event|null $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 */
final class EventRecurrenceRule extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'recurrence_target_type', 'recurrence_target_id',
        'code', 'name', 'description',
        'recurrence_type', 'frequency', 'interval',
        'days_of_week', 'days_of_month', 'months_of_year',
        'starts_on', 'ends_on', 'max_occurrences',
        'timezone', 'time_mode',
        'starts_at_time', 'ends_at_time',
        'anchor_type', 'anchor_code', 'relation', 'offset_minutes',
        'rrule_text', 'human_readable_rule',
        'status', 'visibility',
        'generated_until', 'last_generated_at',
        'disabled_at', 'archived_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_recurrence_rules', 'event_recurrence_rules');
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'generated_until' => 'immutable_datetime',
            'last_generated_at' => 'immutable_datetime',
            'disabled_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'days_of_week' => 'array',
            'days_of_month' => 'array',
            'months_of_year' => 'array',
            'metadata' => 'array',
            'interval' => 'integer',
            'max_occurrences' => 'integer',
            'offset_minutes' => 'integer',
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
     * @return BelongsTo<EventSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function recurrenceTarget(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'recurrence_target_type', 'recurrence_target_id');
    }

    protected static function newFactory(): EventRecurrenceRuleFactory
    {
        return EventRecurrenceRuleFactory::new();
    }
}
