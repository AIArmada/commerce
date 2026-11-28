<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentTemplates
{
    /** @param array<string, mixed> $data */
    public function create(array $data): string;

    /** @param array<string, mixed> $data */
    public function update(string $templateId, array $data): void;

    public function setDefault(string $templateId): void;

    public function remove(string $templateId): void;
}
