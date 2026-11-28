<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentLifecycle
{
    public function changeStatus(string $docId, string $status, ?string $notes = null): bool;

    public function markPaid(string $docId, ?string $notes = null): bool;

    public function cancel(string $docId, ?string $notes = null): bool;

    public function detectOverdue(string $docId): bool;

    /** @param list<string> $docIds */
    public function delete(array $docIds): void;

    /** @param list<string> $docIds
     * @return array{marked: int, skipped: int}
     */
    public function markSent(array $docIds, ?string $notes = null): array;
}
