<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Engagement\Database\Factories\ReminderFactory;
use AIArmada\Engagement\Models\Concerns\UsesEngagementUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $remindable_type
 * @property string $remindable_id
 * @property string $recipient_type
 * @property string $recipient_id
 * @property string $reminder_type
 * @property string $status
 * @property CarbonImmutable|null $remind_at
 * @property int|null $offset_minutes
 * @property string|null $anchor_type
 * @property string|null $anchor_code
 * @property string|null $channel
 * @property string|null $notification_class
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $expires_at
 * @property string|null $failure_reason
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class Reminder extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesEngagementUuid;

    protected static string $ownerScopeConfigKey = 'engagement.owner';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'remindable_type',
        'remindable_id',
        'recipient_type',
        'recipient_id',
        'reminder_type',
        'status',
        'remind_at',
        'offset_minutes',
        'anchor_type',
        'anchor_code',
        'channel',
        'notification_class',
        'scheduled_at',
        'sent_at',
        'cancelled_at',
        'failed_at',
        'expires_at',
        'failure_reason',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('engagement.database.tables.reminders', 'reminders');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Reminder>  $query
     * @return Builder<Reminder>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'scheduled']);
    }

    /**
     * @param  Builder<Reminder>  $query
     * @return Builder<Reminder>
     */
    public function scopeForRecipient(Builder $query, Model $recipient): Builder
    {
        return $query->where('recipient_type', $recipient->getMorphClass())
            ->where('recipient_id', $recipient->getKey());
    }

    /**
     * @param  Builder<Reminder>  $query
     * @return Builder<Reminder>
     */
    public function scopeForRemindable(Builder $query, Model $remindable): Builder
    {
        return $query->where('remindable_type', $remindable->getMorphClass())
            ->where('remindable_id', $remindable->getKey());
    }

    /**
     * @param  Builder<Reminder>  $query
     * @return Builder<Reminder>
     */
    public function scopeReminderType(Builder $query, string $type): Builder
    {
        return $query->where('reminder_type', $type);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offset_minutes' => 'integer',
            'metadata' => 'array',
            'remind_at' => 'immutable_datetime',
            'scheduled_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): ReminderFactory
    {
        return ReminderFactory::new();
    }
}
