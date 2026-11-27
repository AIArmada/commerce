<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Concerns;

use AIArmada\Engagement\Features\Interactions\Api\ReadsInteractionState;
use AIArmada\Engagement\Traits\InteractsWithEngagement;

use AIArmada\Engagement\Features\Interactions\Models\Follow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait CanFollow
{
    use InteractsWithEngagement;

    /**
     * @return MorphMany<Follow, $this>
     */
    public function follows(): MorphMany
    {
        return $this->morphMany(Follow::class, 'follower');
    }

    public function follow(mixed $subject, array $options = []): Follow
    {
        return $this->engagementManager()->follow($this->engagementActor(), $subject, $options);
    }

    public function unfollow(mixed $subject): void
    {
        $this->engagementManager()->unfollow($this->engagementActor(), $subject);
    }

    public function isFollowing(mixed $subject): bool
    {
        return app(ReadsInteractionState::class)->isFollowing($this->engagementActor(), $subject);
    }
}
