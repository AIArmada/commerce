<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface CreativeAdministration
{
    /** @param array<string, mixed> $data */
    public function create(?string $programId, array $data): string;

    /** @param array<string, mixed> $data */
    public function update(?string $programId, string $creativeId, array $data): void;

    public function delete(?string $programId, string $creativeId): bool;
}
