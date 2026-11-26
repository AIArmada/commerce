<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Ingestion\Api;

interface InteractionRuleAdministration
{
    /** @param array<string, mixed> $data */
    public function create(array $data): string;

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data): void;

    public function delete(string $id): bool;
}
