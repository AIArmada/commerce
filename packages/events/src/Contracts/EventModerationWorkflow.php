<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventSubmission;

interface EventModerationWorkflow
{
    public function submit(EventSubmission $submission, mixed $actor = null): void;

    public function approve(EventSubmission $submission, mixed $actor = null, ?string $notes = null): void;

    public function reject(EventSubmission $submission, mixed $actor, string $reason, ?string $notes = null): void;

    public function requestChanges(EventSubmission $submission, mixed $actor, string $reason, ?string $notes = null): void;
}
