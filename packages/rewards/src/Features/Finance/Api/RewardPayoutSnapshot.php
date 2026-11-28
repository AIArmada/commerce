<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Finance\Api;

use AIArmada\Rewards\Enums\RewardPayoutRequestStatus;

final readonly class RewardPayoutSnapshot
{
    public function __construct(
        public string $id,
        public RewardPayoutRequestStatus $status,
        public int $amountMinor,
        public string $currency,
        public bool $hasConfirmation,
    ) {}
}
