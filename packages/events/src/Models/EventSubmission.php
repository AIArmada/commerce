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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $event_id
 * @property string|null $submitted_by_type
 * @property string|null $submitted_by_id
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 */
class EventSubmission extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'events.features.owner';

    protected $fillable = [
        'event_id',
        'submitted_by_type',
        'submitted_by_id',
        'status',
        'submitted_at',
        'notes',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function getTable(): string
    {
        return config('events.database.tables.submissions', 'event_submissions');
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function submittedBy(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'submitted_by_type', 'submitted_by_id');
    }

    /**
     * @return HasMany<EventReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(EventReview::class, 'event_submission_id');
    }

    public function submit(): void
    {
        $this->status = EventModerationStatus::Pending->value;
        $this->submitted_at ??= now();
    }
}
