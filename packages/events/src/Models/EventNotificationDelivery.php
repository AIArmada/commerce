<?php

declare(strict_types=1);

namespace AIArmada\Events\Models;

use AIArmada\Events\Database\Factories\EventNotificationDeliveryFactory;
use AIArmada\Events\Models\Concerns\UsesEventUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_notification_batch_id
 * @property string $recipient_type
 * @property string $recipient_id
 * @property string $channel
 * @property string $status
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $failed_at
 * @property string|null $error_message
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EventNotificationDelivery extends Model
{
    use HasFactory;
    use UsesEventUuid;

    protected $fillable = [
        'event_notification_batch_id',
        'recipient_type', 'recipient_id',
        'channel', 'status',
        'sent_at', 'failed_at', 'error_message',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('events.database.tables.event_notification_deliveries', 'event_notification_deliveries');
    }

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EventNotificationBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(EventNotificationBatch::class, 'event_notification_batch_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): EventNotificationDeliveryFactory
    {
        return EventNotificationDeliveryFactory::new();
    }
}
