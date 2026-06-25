<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Events\EventSubmissionApproved;
use AIArmada\Events\Events\EventSubmissionRejected;
use AIArmada\Events\Models\EventSubmission;
use AIArmada\Events\States\EventModerationStatus\Approved;
use AIArmada\Events\States\EventModerationStatus\ChangesRequested;
use AIArmada\Events\States\EventModerationStatus\Pending;
use AIArmada\Events\States\EventModerationStatus\Rejected;
use AIArmada\Events\Support\EventWriteGuard;
use Carbon\CarbonImmutable;

final class DefaultEventModerationWorkflow implements EventModerationWorkflow
{
    public function submit(EventSubmission $submission, mixed $actor = null): void
    {
        $this->guardSubmissionEvent($submission);

        $submission->submitted_at = CarbonImmutable::now();
        $submission->status->transitionTo(Pending::class);
    }

    public function approve(EventSubmission $submission, mixed $actor = null, ?string $notes = null): void
    {
        $this->guardSubmissionEvent($submission);

        $submission->reviewed_at = CarbonImmutable::now();
        $submission->metadata = $this->mergeReviewMetadata($submission, 'approved', null, $notes);
        $submission->status->transitionTo(Approved::class);

        event(new EventSubmissionApproved($submission));
    }

    public function reject(EventSubmission $submission, mixed $actor, string $reason, ?string $notes = null): void
    {
        $this->guardSubmissionEvent($submission);

        $submission->reviewed_at = CarbonImmutable::now();
        $submission->metadata = $this->mergeReviewMetadata($submission, 'rejected', $reason, $notes);
        $submission->status->transitionTo(Rejected::class);

        event(new EventSubmissionRejected($submission, $reason));
    }

    public function requestChanges(EventSubmission $submission, mixed $actor, string $reason, ?string $notes = null): void
    {
        $this->guardSubmissionEvent($submission);

        $submission->reviewed_at = CarbonImmutable::now();
        $submission->metadata = $this->mergeReviewMetadata($submission, 'changes_requested', $reason, $notes);
        $submission->status->transitionTo(ChangesRequested::class);
    }

    private function guardSubmissionEvent(EventSubmission $submission): void
    {
        if ($submission->event_id === null) {
            return;
        }

        EventWriteGuard::findOrFail($submission->event_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeReviewMetadata(EventSubmission $submission, string $status, ?string $reason, ?string $notes): array
    {
        $metadata = $submission->metadata ?? [];
        $metadata['review'] = array_filter([
            'status' => $status,
            'reason' => $reason,
            'notes' => $notes,
            'reviewed_at' => CarbonImmutable::now()->toIso8601String(),
        ], static fn (mixed $value): bool => $value !== null);

        return $metadata;
    }
}
