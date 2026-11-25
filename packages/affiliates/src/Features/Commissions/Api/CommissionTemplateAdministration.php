<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

interface CommissionTemplateAdministration
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): string;

    /** @param array<string, mixed> $attributes */
    public function update(string $templateId, array $attributes): void;

    public function createStandardPercentage(string $name, int $rateBasisPoints = 1000, bool $isDefault = false): string;

    /** @param list<array{min_volume: int, max_volume: int|null, bonus_rate: int}> $volumeTiers */
    public function createTieredVolume(string $name, int $baseRateBasisPoints = 500, array $volumeTiers = []): string;

    /** @param list<int> $overridePercentages */
    public function createMlm(string $name, int $baseRateBasisPoints = 1000, array $overridePercentages = [50, 25, 10]): string;

    public function applyToAffiliate(string $templateId, string $affiliateId): void;

    public function applyToProgram(string $templateId, string $programId): void;
}
