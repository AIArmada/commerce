<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Benefits\Api;

final readonly class ConsumedRewardBenefit
{
    public function __construct(
        public string $id,
        public string $benefitKey,
    ) {}
}
