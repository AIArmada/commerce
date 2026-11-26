<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

interface PayoutMethodAdministration
{
    /** @param array<string, mixed> $details */
    public function create(string $affiliateId, string $type, array $details, bool $default = false): string;

    /** @param array<string, mixed> $details */
    public function replace(string $affiliateId, string $methodId, string $type, array $details): void;

    public function remove(string $affiliateId, string $methodId): void;

    public function verify(string $affiliateId, string $methodId): void;

    public function selectDefault(string $affiliateId, string $methodId): void;

    public function setDefault(string $affiliateId, string $type, string $label, string $accountRef): void;
}
