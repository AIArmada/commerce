<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Traits;

use AIArmada\Engagement\Models\Bookmark;
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
        return $this->engagementManager()->bookmark($this->engagementActor(), $subject, $options);
    }

    public function removeBookmark(mixed $subject): void
    {
        $this->engagementManager()->removeBookmark($this->engagementActor(), $subject);
    }
}
