<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Data;

final readonly class RewardAwardSnapshot
{
    /** @param array<string, mixed> $amountSnapshot */
    public function __construct(
        public string $id,
        public string $programId,
        public string $recipientType,
        public string $recipientId,
        public string $component,
        public array $amountSnapshot,
        public int $obligationEpoch,
        public int $obligatedMinor,
    ) {}
}
