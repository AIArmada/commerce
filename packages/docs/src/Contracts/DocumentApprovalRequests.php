<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

use DateTimeInterface;

interface DocumentApprovalRequests
{
    public function request(string $docId, string $actorId, ?string $assigneeId = null, DateTimeInterface | string | null $expiresAt = null, ?string $comments = null): string;

    /** @param list<string> $approvalIds */
    public function remove(string $docId, array $approvalIds): void;
}
