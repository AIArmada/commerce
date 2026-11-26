<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

interface ProgramCommissionAdministration
{
    /** @param array<string, mixed> $data */
    public function createRule(string $programId, array $data): string;

    /** @param array<string, mixed> $data */
    public function updateRule(string $programId, string $ruleId, array $data): void;

    public function deleteRule(string $programId, string $ruleId): bool;

    /** @param array<string, mixed> $data */
    public function createPromotion(string $programId, array $data): string;

    /** @param array<string, mixed> $data */
    public function updatePromotion(string $programId, string $promotionId, array $data): void;

    public function deletePromotion(string $programId, string $promotionId): bool;
}
