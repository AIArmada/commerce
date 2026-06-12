<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventReportFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $reportable_type
 * @property string $reportable_id
 * @property string|null $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $reporter_type
 * @property string|null $reporter_id
 * @property string $report_type
 * @property string $status
 * @property string $severity
 * @property string|null $title
 * @property string|null $message
 * @property string|null $reviewed_by_type
 * @property string|null $reviewed_by_id
 * @property CarbonImmutable $reported_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $archived_at
 * @property string|null $resolution
 * @property string|null $internal_notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $reportable
 */
final class EventReport extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'reportable_type', 'reportable_id',
        'event_id', 'event_occurrence_id', 'event_session_id',
        'reporter_type', 'reporter_id',
        'report_type', 'status', 'severity',
        'title', 'message',
        'reviewed_by_type', 'reviewed_by_id',
        'reported_at', 'reviewed_at', 'resolved_at', 'rejected_at', 'archived_at',
        'resolution', 'internal_notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_reports', 'event_reports');
    }

    protected function casts(): array
    {
        return [
            'reported_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
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

    protected static function newFactory(): EventReportFactory
    {
        return EventReportFactory::new();
    }
}
