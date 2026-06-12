<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

use AIArmada\Engagement\Models\Bookmark;

final class BookmarkCreated
{
    public function __construct(public Bookmark $bookmark) {}
}
