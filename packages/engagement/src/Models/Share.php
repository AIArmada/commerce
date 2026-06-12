<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Models;

use AIArmada\Engagement\Database\Factories\ShareFactory;
use AIArmada\Engagement\Models\Concerns\UsesEngagementUuid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string|null $sharer_type
 * @property string|null $sharer_id
 * @property string $shareable_type
 * @property string $shareable_id
 * @property string|null $channel
 * @property string|null $destination
 * @property string|null $share_url
 * @property string|null $share_token
 * @property string|null $message
 * @property string $status
 * @property CarbonInterface|null $share_intent_at
 * @property CarbonInterface|null $shared_at
 * @property CarbonInterface|null $revoked_at
 * @property CarbonInterface|null $expired_at
 * @property CarbonInterface|null $failed_at
 * @property string|null $failure_reason
 * @property array|null $metadata
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
final class Share extends Model
{
    use HasFactory;
    use UsesEngagementUuid;

    public const STATUS_CREATED = 'created';

    public const STATUS_SHARED = 'shared';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'sharer_type', 'sharer_id', 'shareable_type', 'shareable_id',
        'channel', 'destination', 'share_url', 'share_token', 'message',
        'status', 'share_intent_at', 'shared_at', 'revoked_at', 'expired_at',
        'failed_at', 'failure_reason', 'metadata',
    ];

    public function getTable(): string
    {
        return config('engagement.database.tables.shares', 'engagement_shares');
    }

    /** @return MorphTo<Model, $this> */
    public function sharer(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isCreated(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }

    public function isShared(): bool
    {
        return $this->status === self::STATUS_SHARED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'share_intent_at' => 'immutable_datetime',
            'shared_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): ShareFactory
    {
        return ShareFactory::new();
    }
}
