<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentSequenceSettings
{
    /** @param array<string, mixed> $data */
    public function create(array $data): string;

    /** @param array<string, mixed> $data */
    public function update(string $sequenceId, array $data): void;

    /** @param list<string> $sequenceIds */
    public function remove(array $sequenceIds): void;
}
