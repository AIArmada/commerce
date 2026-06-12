<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventRevisionFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $revisable_type
 * @property string $revisable_id
 * @property string|null $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property int $version_no
 * @property string $revision_type
 * @property string $status
 * @property string|null $title
 * @property string|null $summary
 * @property array $payload
 * @property array|null $diff
 * @property string|null $submitted_by_type
 * @property string|null $submitted_by_id
 * @property string|null $reviewed_by_type
 * @property string|null $reviewed_by_id
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $superseded_at
 * @property CarbonImmutable|null $archived_at
 * @property string|null $rejection_reason
 * @property string|null $internal_notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Model $revisable
 * @property-read Event|null $event
 * @property-read EventOccurrence|null $occurrence
 * @property-read EventSession|null $session
 */
final class EventRevision extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'revisable_type', 'revisable_id',
        'event_id', 'event_occurrence_id', 'event_session_id',
        'version_no', 'revision_type', 'status',
        'title', 'summary',
        'payload', 'diff',
        'submitted_by_type', 'submitted_by_id',
        'reviewed_by_type', 'reviewed_by_id',
        'submitted_at', 'approved_at', 'rejected_at',
        'published_at', 'superseded_at', 'archived_at',
        'rejection_reason', 'internal_notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_revisions', 'event_revisions');
    }

    protected function casts(): array
    {
        return [
            'version_no' => 'integer',
            'payload' => 'array',
            'diff' => 'array',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function revisable(): MorphTo
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

    protected static function newFactory(): EventRevisionFactory
    {
        return EventRevisionFactory::new();
    }
}
