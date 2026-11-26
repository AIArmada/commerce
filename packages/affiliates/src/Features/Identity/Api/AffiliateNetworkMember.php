<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

final readonly class AffiliateNetworkMember
{
    public function __construct(
        public AffiliateLifecycleReference $reference,
        public bool $commercial,
        public string $name,
        public string $code,
        public ?string $currency,
        public string $status,
        public ?string $rankId,
        public int $directDownlineCount,
        public int $totalDownlineCount,
    ) {}
}
