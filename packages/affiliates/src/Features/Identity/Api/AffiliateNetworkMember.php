<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

final readonly class AffiliateNetworkMember
{
    public function __construct(
        public AffiliateLifecycleReference $reference,
        public bool $commercial,
    ) {}
}
