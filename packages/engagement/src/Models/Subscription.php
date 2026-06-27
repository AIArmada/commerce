<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Engagement\Database\Factories\SubscriptionFactory;
use AIArmada\Engagement\Enums\SubscriptionStatus;
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
 * @property string $subscriber_type
 * @property string $subscriber_id
 * @property string $subscribable_type
 * @property string $subscribable_id
 * @property string $subscription_type
 * @property string $status
 * @property array|null $criteria
 * @property string|null $notification_level
 * @property array|null $notification_preferences
 * @property CarbonImmutable|null $subscribed_at
 * @property CarbonImmutable|null $muted_at
 * @property CarbonImmutable|null $unsubscribed_at
 * @property CarbonImmutable|null $expires_at
 * @property string|null $source
 * @property array|null $metadata
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class Subscription extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use UsesEngagementUuid;

    protected static string $ownerScopeConfigKey = 'engagement.owner';

    protected $fillable = [
        'subscriber_type',
        'subscriber_id',
        'subscribable_type',
        'subscribable_id',
        'subscription_type',
        'status',
        'criteria',
        'notification_level',
        'notification_preferences',
        'subscribed_at',
        'muted_at',
        'unsubscribed_at',
        'expires_at',
        'source',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('engagement.database.tables.subscriptions', 'subscriptions');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Subscription>  $query
     * @return Builder<Subscription>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Active);
    }

    /**
     * @param  Builder<Subscription>  $query
     * @return Builder<Subscription>
     */
    public function scopeForSubscriber(Builder $query, Model $subscriber): Builder
    {
        return $query->where('subscriber_type', $subscriber->getMorphClass())
            ->where('subscriber_id', $subscriber->getKey());
    }

    /**
     * @param  Builder<Subscription>  $query
     * @return Builder<Subscription>
     */
    public function scopeForSubscribable(Builder $query, Model $subscribable): Builder
    {
        return $query->where('subscribable_type', $subscribable->getMorphClass())
            ->where('subscribable_id', $subscribable->getKey());
    }

    /**
     * @param  Builder<Subscription>  $query
     * @return Builder<Subscription>
     */
    public function scopeSubscriptionType(Builder $query, string $type): Builder
    {
        return $query->where('subscription_type', $type);
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    public function isMuted(): bool
    {
        return $this->status === SubscriptionStatus::Muted;
    }

    public function isUnsubscribed(): bool
    {
        return $this->status === SubscriptionStatus::Unsubscribed;
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'criteria' => 'array',
            'notification_preferences' => 'array',
            'metadata' => 'array',
            'subscribed_at' => 'immutable_datetime',
            'muted_at' => 'immutable_datetime',
            'unsubscribed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
