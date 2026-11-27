<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Features\Interactions\Concerns;

use AIArmada\Engagement\Features\Interactions\Actions\ManageBookmarksAction;
use AIArmada\Engagement\Features\Interactions\Models\Bookmark;
use AIArmada\Engagement\Traits\InteractsWithEngagement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait CanBookmark
{
    use InteractsWithEngagement;

    /**
     * @return MorphMany<Bookmark, $this>
     */
    public function bookmarks(): MorphMany
    {
        return $this->morphMany(Bookmark::class, 'bookmarker');
    }

    public function bookmark(mixed $subject, array $options = []): Bookmark
    {
        return app(ManageBookmarksAction::class)->bookmark($this->engagementActor(), $subject, $options);
    }

    public function removeBookmark(mixed $subject): void
    {
        app(ManageBookmarksAction::class)->removeBookmark($this->engagementActor(), $subject);
    }
}
