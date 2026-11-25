<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface EventModerationWorkflow
{
    public function submit(string $submissionId, mixed $actor = null): void;

    public function approve(string $submissionId, mixed $actor = null, ?string $notes = null): void;

    public function reject(string $submissionId, mixed $actor, string $reason, ?string $notes = null): void;

    public function requestChanges(string $submissionId, mixed $actor, string $reason, ?string $notes = null): void;
}
