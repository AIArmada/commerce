<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Api;

final readonly class RewardPayoutAwardSnapshot
{
    /** @param array<string, mixed> $amountSnapshot */
    public function __construct(
        public string $id,
        public string $programId,
        public string $recipientType,
        public string $recipientId,
        public string $component,
        public string $status,
        public array $amountSnapshot,
        public int $obligationEpoch,
        public int $obligatedMinor,
    ) {}
}
