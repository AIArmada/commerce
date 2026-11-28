<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentWorkflows
{
    /** @param array<string, mixed> $data */
    public function create(array $data): string;

    /** @param array<string, mixed> $data */
    public function addStep(string $workflowId, array $data): string;

    public function remove(string $workflowId): void;
}
