<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

final readonly class AffiliateBonusCandidate
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $currency,
        public int $recruitCount = 0,
    ) {}
}
