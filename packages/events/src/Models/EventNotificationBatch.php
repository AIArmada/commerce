<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventNotificationBatchFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
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
 * @property string|null $event_update_id
 * @property string|null $event_change_log_id
 * @property string|null $audience_scope
 * @property string $title
 * @property string|null $message
 * @property array|null $channels
 * @property string $status
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $created_by_type
 * @property string|null $created_by_id
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EventNotificationDelivery> $deliveries
 */
final class EventNotificationBatch extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_id', 'event_occurrence_id', 'event_session_id',
        'event_update_id', 'event_change_log_id',
        'audience_scope',
        'title', 'message', 'channels',
        'status',
        'scheduled_at', 'sent_at', 'cancelled_at',
        'created_by_type', 'created_by_id',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_notification_batches', 'event_notification_batches');
    }

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'scheduled_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
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
     * @return BelongsTo<EventUpdate, $this>
     */
    public function eventUpdate(): BelongsTo
    {
        return $this->belongsTo(EventUpdate::class, 'event_update_id');
    }

    /**
     * @return BelongsTo<EventChangeLog, $this>
     */
    public function changeLog(): BelongsTo
    {
        return $this->belongsTo(EventChangeLog::class, 'event_change_log_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<EventNotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(EventNotificationDelivery::class);
    }

    protected static function newFactory(): EventNotificationBatchFactory
    {
        return EventNotificationBatchFactory::new();
    }
}
