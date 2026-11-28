<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventApprovalWorkflow
{
    public function approve(string $requestId, mixed $actor, ?string $notes = null): void;

    public function reject(string $requestId, mixed $actor, string $reason, ?string $notes = null): void;

    public function assignToActor(string $requestId, mixed $actor): void;
}
