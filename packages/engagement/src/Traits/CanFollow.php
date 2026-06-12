<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Contracts\EngagementStateResolver;
use AIArmada\Engagement\Models\Follow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait CanFollow
{
    /**
     * @return MorphMany<Follow, $this>
     */
    public function follows(): MorphMany
    {
        return $this->morphMany(Follow::class, 'follower');
    }

    public function follow(mixed $subject, array $options = []): Follow
    {
        return app(EngagementManager::class)->follow($this, $subject, $options);
    }

    public function unfollow(mixed $subject): void
    {
        app(EngagementManager::class)->unfollow($this, $subject);
    }

    public function isFollowing(mixed $subject): bool
    {
        return app(EngagementStateResolver::class)->isFollowing($this, $subject);
    }
}
