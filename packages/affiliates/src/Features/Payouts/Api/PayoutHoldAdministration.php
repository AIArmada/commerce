<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

use DateTimeImmutable;

interface PayoutHoldAdministration
{
    public function place(string $affiliateId, string $reason, ?string $notes = null, ?DateTimeImmutable $expiresAt = null, ?string $placedBy = null): string;

    public function release(string $affiliateId, string $holdId, ?string $notes = null): void;
}
