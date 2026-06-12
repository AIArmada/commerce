<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Models\Bookmark;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** @mixin Model */
trait CanBookmark
{
    /**
     * @return MorphMany<Bookmark, $this>
     */
    public function bookmarks(): MorphMany
    {
        return $this->morphMany(Bookmark::class, 'bookmarker');
    }

    public function bookmark(mixed $subject, array $options = []): Bookmark
    {
        return app(EngagementManager::class)->bookmark($this, $subject, $options);
    }

    public function removeBookmark(mixed $subject): void
    {
        app(EngagementManager::class)->removeBookmark($this, $subject);
    }
}
