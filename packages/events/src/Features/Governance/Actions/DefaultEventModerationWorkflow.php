<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Actions;

use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Events\EventSubmissionApproved;
use AIArmada\Events\Events\EventSubmissionRejected;
use AIArmada\Events\Features\Governance\States\EventModerationStatus\Approved;
use AIArmada\Events\Features\Governance\States\EventModerationStatus\ChangesRequested;
use AIArmada\Events\Features\Governance\States\EventModerationStatus\EventModerationStatus as EventModerationStatusState;
use AIArmada\Events\Features\Governance\States\EventModerationStatus\Pending;
use AIArmada\Events\Features\Governance\States\EventModerationStatus\Rejected;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DefaultEventModerationWorkflow implements EventModerationWorkflow
{
    public function submit(string $submissionId, mixed $actor = null): void
    {
        $this->mutate($submissionId, function (Model $submission): void {
            $submission->setAttribute('submitted_at', CarbonImmutable::now());

            $this->transition($submission, Pending::class);
        });
    }

    public function approve(string $submissionId, mixed $actor = null, ?string $notes = null): void
    {
        $submission = $this->mutate($submissionId, function (Model $submission) use ($notes): void {
            $submission->setAttribute('reviewed_at', CarbonImmutable::now());
            $submission->setAttribute('review_reason', null);
            $submission->setAttribute('review_notes', $notes);

            $this->transition($submission, Approved::class);
        });

        $eventId = $submission->getAttribute('event_id');

        if ($eventId !== null) {
            DB::afterCommit(static fn () => event(new EventSubmissionApproved($submissionId, (string) $eventId)));
        }
    }

    public function reject(string $submissionId, mixed $actor, string $reason, ?string $notes = null): void
    {
        $submission = $this->mutate($submissionId, function (Model $submission) use ($reason, $notes): void {
            $submission->setAttribute('reviewed_at', CarbonImmutable::now());
            $submission->setAttribute('review_reason', $reason);
            $submission->setAttribute('review_notes', $notes);

            $this->transition($submission, Rejected::class);
        });

        $eventId = $submission->getAttribute('event_id');

        if ($eventId !== null) {
            DB::afterCommit(static fn () => event(new EventSubmissionRejected($submissionId, (string) $eventId, $reason)));
        }
    }

    public function requestChanges(string $submissionId, mixed $actor, string $reason, ?string $notes = null): void
    {
        $this->mutate($submissionId, function (Model $submission) use ($reason, $notes): void {
            $submission->setAttribute('reviewed_at', CarbonImmutable::now());
            $submission->setAttribute('review_reason', $reason);
            $submission->setAttribute('review_notes', $notes);

            $this->transition($submission, ChangesRequested::class);
        });
    }

    /**
     * @param  callable(Model): void  $mutator
     */
    private function mutate(string $submissionId, callable $mutator): Model
    {
        $submissionClass = ModelResolver::submissionClass();

        return DB::transaction(function () use ($submissionClass, $submissionId, $mutator): Model {
            $submission = $submissionClass::query()
                ->whereKey($submissionId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->guardSubmissionEvent($submission);

            $mutator($submission);

            return $submission;
        });
    }

    /**
     * @param  class-string<EventModerationStatusState>  $stateClass
     */
    private function transition(Model $submission, string $stateClass): void
    {
        $state = $submission->getAttribute('status');

        if (! $state instanceof EventModerationStatusState) {
            throw new RuntimeException(sprintf(
                'Event submission [%s] does not expose a moderation status state.',
                (string) $submission->getKey(),
            ));
        }

        $state->transitionTo($stateClass);
    }

    private function guardSubmissionEvent(Model $submission): void
    {
        $eventId = $submission->getAttribute('event_id');

        if ($eventId === null) {
            return;
        }

        EventWriteGuard::findOrFail((string) $eventId);
    }
}
