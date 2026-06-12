<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Models\Bookmark;
use AIArmada\Engagement\Models\BookmarkCollection;

final class BookmarkAddedToCollection
{
    public function __construct(public Bookmark $bookmark, public BookmarkCollection $collection) {}
}
