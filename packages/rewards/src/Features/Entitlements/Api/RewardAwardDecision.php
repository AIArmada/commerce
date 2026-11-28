<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Api;

final readonly class RewardAwardDecision
{
    public function __construct(
        public string $id,
        public string $recipientType,
        public string $recipientId,
        public string $status,
    ) {}
}
