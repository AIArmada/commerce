<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Data;


final readonly class ClickFraudEvidence
{
    public function __construct(
        public ?string $ipAddress,
        public string $fingerprint,
        public ?int $clickCount,
        public int $clickLimit,
        public ?int $fingerprintCount,
        public int $fingerprintLimit,
        public ?string $previousIp,
        public ?float $minutesSincePreviousIp,
    ) {}
}
