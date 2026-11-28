<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentEmailTemplates
{
    /** @param array<string, mixed> $data */
    public function create(array $data): string;

    /** @param array<string, mixed> $data */
    public function update(string $templateId, array $data): void;

    public function duplicate(string $templateId): string;

    /** @param list<string> $templateIds */
    public function remove(array $templateIds): void;
}
