<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Programs\Api;

final readonly class RewardProgramSnapshot
{
    public function __construct(
        public string $id,
        public string $familyKey,
        public string $status,
    ) {}
}
