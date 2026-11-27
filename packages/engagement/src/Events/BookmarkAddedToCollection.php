<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

final readonly class BookmarkAddedToCollection
{
    public function __construct(
        public string $bookmarkId,
        public string $collectionId,
        public ?string $ownerType,
        public ?string $ownerId,
    ) {}
}
