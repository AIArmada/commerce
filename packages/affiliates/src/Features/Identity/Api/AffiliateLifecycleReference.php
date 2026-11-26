<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

final readonly class AffiliateLifecycleReference
{
    public function __construct(
        public string $id,
        public ?string $ownerType,
        public ?string $ownerId,
    ) {}

    public function matches(self $canonical): bool
    {
        return $this->id === $canonical->id
            && $this->ownerType === $canonical->ownerType
            && $this->ownerId === $canonical->ownerId;
    }
}
