<?php

declare(strict_types=1);

namespace AIArmada\Communications\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Communications\Enums\NotificationFamily;
use AIArmada\Communications\Enums\NotificationPriority;
use AIArmada\Communications\Enums\NotificationTrigger;
use Carbon\CarbonImmutable;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string|null $recipient_type
 * @property string|null $recipient_id
 * @property string|null $communication_id
 * @property NotificationFamily $family
 * @property NotificationPriority $priority
 * @property NotificationTrigger $trigger
 * @property string $title
 * @property string|null $body
 * @property array|null $data
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable|null $scheduled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $recipient
 * @property-read Communication|null $communication
 * @property-read Model|Eloquent $owner
 */
final class NotificationInbox extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'communication_id',
        'family',
        'priority',
        'trigger',
        'title',
        'body',
        'data',
        'read_at',
        'archived_at',
        'scheduled_at',
    ];

    protected static string $ownerScopeConfigKey = 'communications.features.owner';

    public function getTable(): string
    {
        return config('communications.database.tables.notification_inboxes', 'notification_inboxes');
    }

    protected function casts(): array
    {
        return [
            'family' => NotificationFamily::class,
            'priority' => NotificationPriority::class,
            'trigger' => NotificationTrigger::class,
            'data' => 'array',
            'read_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'scheduled_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Communication, $this>
     */
    public function communication(): BelongsTo
    {
        return $this->belongsTo(Communication::class, 'communication_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }
}
