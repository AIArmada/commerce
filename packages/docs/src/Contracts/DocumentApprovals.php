<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentApprovals
{
    public function decide(string $docId, string $approvalId, string $actorId, bool $approve, ?string $comments = null, bool $canDecideUnassigned = false): void;
}
