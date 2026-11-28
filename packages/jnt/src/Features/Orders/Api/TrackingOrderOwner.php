<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Api;

final readonly class TrackingOrderOwner
{
    public function __construct(
        public ?string $ownerType,
        public ?string $ownerId,
    ) {}
}
