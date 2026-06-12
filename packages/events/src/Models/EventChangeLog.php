<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventChangeLogFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string|null $event_occurrence_id
 * @property string|null $event_session_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string $change_type
 * @property string|null $change_category
 * @property mixed|null $old_value
 * @property mixed|null $new_value
 * @property string|null $reason
 * @property string|null $internal_notes
 * @property string|null $impact_level
 * @property string $visibility
 * @property bool $requires_notification
 * @property string|null $changed_by_type
 * @property string|null $changed_by_id
 * @property CarbonImmutable $changed_at
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property-read Event $event
 * @property-read Collection<int, EventUpdate> $updates
 * @property-read Collection<int, EventNotificationBatch> $notificationBatches
 */
final class EventChangeLog extends Model
{
    use HasFactory;
    use UsesEventUuid;

    public const UPDATED_AT = null;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'subject_type', 'subject_id',
        'change_type', 'change_category',
        'old_value', 'new_value',
        'reason', 'internal_notes',
        'impact_level', 'visibility',
        'requires_notification',
        'changed_by_type', 'changed_by_id',
        'changed_at',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_change_logs', 'event_change_logs');
    }

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'requires_notification' => 'boolean',
            'changed_at' => 'immutable_datetime',
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
     * @return BelongsTo<EventSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function changedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<EventUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(EventUpdate::class, 'event_change_log_id');
    }

    /**
     * @return HasMany<EventNotificationBatch, $this>
     */
    public function notificationBatches(): HasMany
    {
        return $this->hasMany(EventNotificationBatch::class, 'event_change_log_id');
    }

    /**
     * @return HasOne<EventUpdate, $this>
     */
    public function eventUpdate(): HasOne
    {
        return $this->hasOne(EventUpdate::class, 'event_change_log_id');
    }

    protected static function newFactory(): EventChangeLogFactory
    {
        return EventChangeLogFactory::new();
    }
}
