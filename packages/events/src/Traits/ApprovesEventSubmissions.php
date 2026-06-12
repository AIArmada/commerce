<?php

declare(strict_types=1);

namespace AIArmada\Events\Traits;

use Carbon\CarbonImmutable;
use AIArmada\Events\Models\EventSubmission;

trait ApprovesEventSubmissions
{
    public function approveSubmission(EventSubmission $submission, ?string $notes = null): void
    {
        $submission->update([
            'status' => 'approved',
            'reviewed_at' => CarbonImmutable::now(),
            'metadata' => $this->mergeSubmissionReviewMetadata($submission, 'approved', null, $notes),
        ]);
    }

    public function rejectSubmission(EventSubmission $submission, string $reason, ?string $notes = null): void
    {
        $submission->update([
            'status' => 'rejected',
            'reviewed_at' => CarbonImmutable::now(),
            'metadata' => $this->mergeSubmissionReviewMetadata($submission, 'rejected', $reason, $notes),
        ]);
    }

    public function requestSubmissionChanges(EventSubmission $submission, string $reason, ?string $notes = null): void
    {
        $submission->update([
            'status' => 'changes_requested',
            'reviewed_at' => CarbonImmutable::now(),
            'metadata' => $this->mergeSubmissionReviewMetadata($submission, 'changes_requested', $reason, $notes),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeSubmissionReviewMetadata(EventSubmission $submission, string $status, ?string $reason, ?string $notes): array
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
