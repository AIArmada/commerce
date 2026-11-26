<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface ProgramTierAdministration
{
    /** @param array<string, mixed> $data */
    public function create(string $programId, array $data): string;

    /** @param array<string, mixed> $data */
    public function update(string $tierId, array $data): void;

    public function delete(string $tierId): void;
}
