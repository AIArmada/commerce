<?php

declare(strict_types=1);

namespace AIArmada\Engagement\Events;

final readonly class FollowRemoved
{
    public function __construct(
        public string $followId,
        public string $actorType,
        public string $actorId,
        public string $subjectType,
        public string $subjectId,
        public ?string $ownerType,
        public ?string $ownerId,
    ) {}
}
