<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Engagement\Database\Factories\FollowFactory;
use AIArmada\Engagement\Enums\FollowStatus;
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
 * @property string $follower_type
 * @property string $follower_id
 * @property string $followable_type
 * @property string $followable_id
 * @property string $status
 * @property string|null $notification_level
 * @property array|null $notification_preferences
 * @property CarbonImmutable|null $followed_at
 * @property CarbonImmutable|null $muted_at
 * @property CarbonImmutable|null $unfollowed_at
 * @property CarbonImmutable|null $blocked_at
 * @property string|null $source
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class Follow extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesEngagementUuid;

    protected static string $ownerScopeConfigKey = 'engagement.owner';

    protected $fillable = [
        'follower_type',
        'follower_id',
        'followable_type',
        'followable_id',
        'status',
        'notification_level',
        'notification_preferences',
        'followed_at',
        'muted_at',
        'unfollowed_at',
        'blocked_at',
        'source',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('engagement.database.tables.follows', 'follows');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function follower(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function followable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Follow>  $query
     * @return Builder<Follow>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FollowStatus::Active);
    }

    /**
     * @param  Builder<Follow>  $query
     * @return Builder<Follow>
     */
    public function scopeMuted(Builder $query): Builder
    {
        return $query->where('status', FollowStatus::Muted);
    }

    /**
     * @param  Builder<Follow>  $query
     * @return Builder<Follow>
     */
    public function scopeUnfollowed(Builder $query): Builder
    {
        return $query->where('status', FollowStatus::Unfollowed);
    }

    /**
     * @param  Builder<Follow>  $query
     * @return Builder<Follow>
     */
    public function scopeForFollower(Builder $query, Model $follower): Builder
    {
        return $query->where('follower_type', $follower->getMorphClass())
            ->where('follower_id', $follower->getKey());
    }

    /**
     * @param  Builder<Follow>  $query
     * @return Builder<Follow>
     */
    public function scopeForFollowable(Builder $query, Model $followable): Builder
    {
        return $query->where('followable_type', $followable->getMorphClass())
            ->where('followable_id', $followable->getKey());
    }

    /**
     * @param  Builder<Follow>  $query
     * @return Builder<Follow>
     */
    public function scopeNotificationLevel(Builder $query, string $level): Builder
    {
        return $query->where('notification_level', $level);
    }

    public function isActive(): bool
    {
        return $this->status === FollowStatus::Active;
    }

    public function isMuted(): bool
    {
        return $this->status === FollowStatus::Muted;
    }

    public function isUnfollowed(): bool
    {
        return $this->status === FollowStatus::Unfollowed;
    }

    protected static function newFactory(): FollowFactory
    {
        return FollowFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FollowStatus::class,
            'notification_preferences' => 'array',
            'metadata' => 'array',
            'followed_at' => 'immutable_datetime',
            'muted_at' => 'immutable_datetime',
            'unfollowed_at' => 'immutable_datetime',
            'blocked_at' => 'immutable_datetime',
        ];
    }
}
