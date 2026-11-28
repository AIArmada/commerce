<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Api;

final readonly class RewardPayoutSnapshot
{
    public function __construct(
        public string $id,
        public string $status,
        public int $amountMinor,
        public string $currency,
        public bool $hasConfirmation,
    ) {}
}
