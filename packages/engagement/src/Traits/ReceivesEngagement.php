<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Contracts\EngagementCounterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;

/**
 * Internal subject-side implementation shared by the public Has* traits.
 *
 * @mixin Model
 */
trait ReceivesEngagement
{
    /**
     * @param  class-string<Model>  $related
     * @return MorphMany<Model, $this>
     */
    protected function engagementRelation(string $related, string $morphName): MorphMany
    {
        return $this->morphMany($related, $morphName);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function activeEngagementScope(Builder $query, string $relation): Builder
    {
        return $query->whereHas($relation, function (Builder $related): void {
            $related->where('status', 'active');
        });
    }

    protected function engagementCount(string $counterType, ?string $counterKey = null): int
    {
        if (! $this instanceof Model) {
            throw new InvalidArgumentException('Engagement subjects must be Eloquent models.');
        }

        $counter = app(EngagementCounterService::class);

        return match ($counterType) {
            'followers' => $counter->countFollowers($this),
            'bookmarks' => $counter->countBookmarks($this),
            'responses' => $counter->countResponses($this, $counterKey),
            'reactions' => $counter->countReactions($this, $counterKey),
            default => $counter->value($this, $counterType, $counterKey ?? ''),
        };
    }
}
