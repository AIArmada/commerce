<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Events\EventSubmissionApproved;
use AIArmada\Events\Events\EventSubmissionRejected;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSubmission;
use Carbon\CarbonImmutable;

final class DefaultEventModerationWorkflow implements EventModerationWorkflow
{
    public function submit(EventSubmission $submission, mixed $actor = null): void
    {
        $this->guardSubmissionEvent($submission);

        $submission->update([
            'status' => 'pending',
            'submitted_at' => CarbonImmutable::now(),
        ]);
    }

    public function approve(EventSubmission $submission, mixed $actor = null, ?string $notes = null): void
    {
        $this->guardSubmissionEvent($submission);

        $submission->update([
            'status' => 'approved',
            'reviewed_at' => CarbonImmutable::now(),
            'metadata' => $this->mergeReviewMetadata($submission, 'approved', null, $notes),
        ]);

        event(new EventSubmissionApproved($submission));
    }

    public function reject(EventSubmission $submission, mixed $actor, string $reason, ?string $notes = null): void
    {
        $this->guardSubmissionEvent($submission);

        $submission->update([
            'status' => 'rejected',
            'reviewed_at' => CarbonImmutable::now(),
            'metadata' => $this->mergeReviewMetadata($submission, 'rejected', $reason, $notes),
        ]);

        event(new EventSubmissionRejected($submission, $reason));
    }

    public function requestChanges(EventSubmission $submission, mixed $actor, string $reason, ?string $notes = null): void
    {
        $this->guardSubmissionEvent($submission);

        $submission->update([
            'status' => 'changes_requested',
            'reviewed_at' => CarbonImmutable::now(),
            'metadata' => $this->mergeReviewMetadata($submission, 'changes_requested', $reason, $notes),
        ]);
    }

    private function guardSubmissionEvent(EventSubmission $submission): void
    {
        if ($submission->event_id === null) {
            return;
        }

        OwnerWriteGuard::findOrFailForOwner(Event::class, $submission->event_id);
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
