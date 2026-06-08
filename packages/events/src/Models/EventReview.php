<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Events\Enums\EventModerationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $event_id
 * @property string|null $event_submission_id
 * @property string|null $reviewed_by_type
 * @property string|null $reviewed_by_id
 * @property EventModerationStatus $decision
 * @property string|null $reason_key
 * @property string|null $notes
 * @property Carbon|null $reviewed_at
 * @property array<string, mixed>|null $before_snapshot
 * @property array<string, mixed>|null $after_snapshot
 * @property array<string, mixed>|null $metadata
 */
class EventReview extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'event_id',
        'event_submission_id',
        'reviewed_by_type',
        'reviewed_by_id',
        'decision',
        'reason_key',
        'notes',
        'reviewed_at',
        'before_snapshot',
        'after_snapshot',
        'metadata',
    ];

    protected $attributes = [
        'decision' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'metadata' => 'array',
            'decision' => EventModerationStatus::class,
        ];
    }

    public function getTable(): string
    {
        return config('events.database.tables.reviews', 'event_reviews');
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<EventSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(EventSubmission::class, 'event_submission_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reviewedBy(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reviewed_by_type', 'reviewed_by_id');
    }

    public function isDecision(EventModerationStatus $status): bool
    {
        return $this->decision === $status;
    }
}
