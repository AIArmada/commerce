<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Models\Bookmark;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait HasBookmarks
{
    use ReceivesEngagement;

    /**
     * @return MorphMany<Bookmark, $this>
     */
    public function bookmarks(): MorphMany
    {
        return $this->engagementRelation(Bookmark::class, 'bookmarkable');
    }

    /**
     * @param  Builder<Bookmark>  $query
     * @return Builder<Bookmark>
     */
    public function scopeActiveBookmarks(Builder $query): Builder
    {
        return $this->activeEngagementScope($query, 'bookmarks');
    }
}
