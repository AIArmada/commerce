<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Features\Entitlements\Api;

interface RewardPointsEntitlementApi
{
    public function connectionName(): string;

    public function evaluatePoints(string $businessKey, string $recipientType, string $recipientId, string $role, ?string $familyKey, ?int $expectedPoints): void;

    /** @param array<string, mixed> $evidence */
    public function reversePoints(string $businessKey, string $recipientType, string $recipientId, string $role, string $reason, array $evidence): void;
}
