<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Concerns;
use AIArmada\Engagement\Features\Interactions\Api\ReadsInteractionState;
use AIArmada\Engagement\Features\Interactions\Actions\ManageFollowsAction;
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
        return app(ManageFollowsAction::class)->follow($this->engagementActor(), $subject, $options);
    }

    public function unfollow(mixed $subject): void
    {
        app(ManageFollowsAction::class)->unfollow($this->engagementActor(), $subject);
    }

    public function isFollowing(mixed $subject): bool
    {
        return app(ReadsInteractionState::class)->isFollowing($this->engagementActor(), $subject);
    }
}
