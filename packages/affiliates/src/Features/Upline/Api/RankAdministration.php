<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Api;

interface RankAdministration
{
    /** @param array<string, mixed> $data */
    public function create(array $data): string;

    /** @param array<string, mixed> $data */
    public function update(string $rankId, array $data): void;

    public function delete(string $rankId): bool;
}
