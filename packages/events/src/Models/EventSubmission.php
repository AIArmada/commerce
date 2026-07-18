<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventSubmissionFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use AIArmada\Events\States\EventModerationStatus\EventModerationStatus as EventModerationStatusState;
use AIArmada\Events\Support\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string|null $submitter_type
 * @property string|null $submitter_id
 * @property string|null $target_type
 * @property string|null $target_id
 * @property string|null $event_id
 * @property string|null $event_occurrence_id
 * @property mixed|null $submission_data
 * @property EventModerationStatusState $status
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_reason
 * @property string|null $review_notes
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EventSubmission extends Model
{
    use HasFactory;
    use HasStates;
    use UsesEventUuid;

    protected $fillable = [
        'submitter_type', 'submitter_id',
        'target_type', 'target_id',
        'event_id', 'event_occurrence_id',
        'submission_data',
        'status',
        'submitted_at', 'reviewed_at',
        'review_reason', 'review_notes',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_submissions', 'event_submissions');
    }

    protected function casts(): array
    {
        return [
            'status' => EventModerationStatusState::class,
            'submission_data' => 'array',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function submitter(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::eventClass());
    }

    /**
     * @return BelongsTo<EventOccurrence, $this>
     */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    /**
     * @return HasMany<EventSubmissionLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(EventSubmissionLog::class);
    }

    /**
     * @return HasMany<EventSubmissionAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EventSubmissionAttachment::class);
    }

    /**
     * @return MorphMany<EventApprovalRequest, $this>
     */
    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(EventApprovalRequest::class, 'approvable');
    }

    protected static function newFactory(): EventSubmissionFactory
    {
        return EventSubmissionFactory::new();
    }
}
