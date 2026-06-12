<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventRevision;

interface EventRevisionManager
{
    public function createRevision(mixed $revisable, array $payload, array $context = []): EventRevision;

    public function approveRevision(EventRevision $revision, mixed $approver = null): void;

    public function publishRevision(EventRevision $revision): mixed;

    public function rejectRevision(EventRevision $revision, string $reason, mixed $reviewer = null): void;
}
