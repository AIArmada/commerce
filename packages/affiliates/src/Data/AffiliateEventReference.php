<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;

final readonly class AffiliateEventReference
{
    public function __construct(
        public string $id,
        public string $affiliateId,
        public string $affiliateCode,
        public ?string $ownerType,
        public ?string $ownerId,
    ) {}

    public function matches(self $canonical): bool
    {
        return $this->id === $canonical->id
            && $this->affiliateId === $canonical->affiliateId
            && $this->affiliateCode === $canonical->affiliateCode
            && $this->ownerType === $canonical->ownerType
            && $this->ownerId === $canonical->ownerId;
    }
}
