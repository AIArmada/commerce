<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use AIArmada\Events\Models\EventSubmission;
use AIArmada\Events\States\EventModerationStatus\Approved;
use AIArmada\Events\States\EventModerationStatus\ChangesRequested;
use AIArmada\Events\States\EventModerationStatus\Rejected;
use Carbon\CarbonImmutable;

trait ApprovesEventSubmissions
{
    public function approveSubmission(EventSubmission $submission, ?string $notes = null): void
    {
        $submission->status->transitionTo(Approved::class);
        $submission->update([
            'reviewed_at' => CarbonImmutable::now(),
            'review_reason' => null,
            'review_notes' => $notes,
        ]);
    }

    public function rejectSubmission(EventSubmission $submission, string $reason, ?string $notes = null): void
    {
        $submission->status->transitionTo(Rejected::class);
        $submission->update([
            'reviewed_at' => CarbonImmutable::now(),
            'review_reason' => $reason,
            'review_notes' => $notes,
        ]);
    }

    public function requestSubmissionChanges(EventSubmission $submission, string $reason, ?string $notes = null): void
    {
        $submission->status->transitionTo(ChangesRequested::class);
        $submission->update([
            'reviewed_at' => CarbonImmutable::now(),
            'review_reason' => $reason,
            'review_notes' => $notes,
        ]);
    }
}
