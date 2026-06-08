<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Events\EventModerationTransitioned;
use AIArmada\Events\Events\EventSubmissionCreated;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventReview;
use AIArmada\Events\Models\EventSubmission;
use AIArmada\Events\Support\EventModerationPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DefaultEventModerationWorkflow implements EventModerationWorkflow
{
    public function submit(Event $event, ?Model $actor = null, array $context = []): EventSubmission
    {
        $actionKey = 'submit';
        $fromStatus = $event->moderation_status instanceof EventModerationStatus
            ? $event->moderation_status
            : EventModerationStatus::Pending;
        $note = $this->contextString($context, ['note', 'notes']);
        $reasonKey = $this->contextString($context, ['reason_key', 'reason']);

        $submission = DB::transaction(function () use ($event, $actor, $context, $actionKey, $fromStatus, $note, $reasonKey): EventSubmission {
            $submission = new EventSubmission;
            $submission->event()->associate($event);

            if ($actor !== null) {
                $submission->submittedBy()->associate($actor);
            }

            $submission->fill([
                'status' => EventModerationStatus::Pending->value,
                'submitted_at' => now(),
                'notes' => $note,
                'metadata' => $this->buildMetadata($context, $actionKey, $fromStatus, EventModerationStatus::Pending, $actor, $note, $reasonKey),
            ]);
            $submission->save();

            $event->forceFill([
                'moderation_status' => EventModerationStatus::Pending,
            ])->save();

            return $submission;
        });

        event(new EventSubmissionCreated($event->fresh() ?? $event, $submission, $context));

        return $submission;
    }

    public function transition(Event $event, EventModerationStatus $decision, ?Model $actor = null, array $context = []): EventReview
    {
        $actionKey = $this->actionKey($context, $decision);
        $note = $this->contextString($context, ['note', 'notes']);
        $reasonKey = $this->contextString($context, ['reason_key', 'reason']);

        [$submission, $review, $fromStatus] = DB::transaction(function () use ($event, $decision, $actor, $context, $actionKey, $note, $reasonKey): array {
            $submission = $event->submissions()->latest('submitted_at')->first();

            if ($submission === null) {
                $submission = $this->createSubmission($event, $actor, $context);
            }

            $fromStatus = $event->moderation_status instanceof EventModerationStatus
                ? $event->moderation_status
                : EventModerationStatus::Pending;

            $this->assertTransitionIsAllowed($actionKey, $fromStatus, $decision, $note, $reasonKey);

            $review = new EventReview;
            $review->event()->associate($event);
            $review->submission()->associate($submission);

            if ($actor !== null) {
                $review->reviewedBy()->associate($actor);
            }

            $review->fill([
                'decision' => $decision,
                'reason_key' => $reasonKey,
                'notes' => $note,
                'reviewed_at' => now(),
                'before_snapshot' => $event->toArray(),
                'after_snapshot' => $this->afterSnapshot($event, $decision),
                'metadata' => $this->buildMetadata($context, $actionKey, $fromStatus, $decision, $actor, $note, $reasonKey),
            ]);
            $review->save();

            $event->forceFill([
                'moderation_status' => $decision,
            ])->save();

            return [$submission, $review, $fromStatus];
        });

        event(new EventModerationTransitioned($event->fresh() ?? $event, $submission, $review, $fromStatus, $decision, $context));

        return $review;
    }

    public function approve(Event $event, ?Model $actor = null, array $context = []): EventReview
    {
        return $this->transition($event, EventModerationStatus::Approved, $actor, array_merge($context, ['transition' => 'approve']));
    }

    public function requestChanges(Event $event, ?Model $actor = null, array $context = []): EventReview
    {
        return $this->transition($event, EventModerationStatus::ChangesRequested, $actor, array_merge($context, ['transition' => 'request_changes']));
    }

    public function reject(Event $event, ?Model $actor = null, array $context = []): EventReview
    {
        return $this->transition($event, EventModerationStatus::Rejected, $actor, array_merge($context, ['transition' => 'reject']));
    }

    public function cancel(Event $event, ?Model $actor = null, array $context = []): EventReview
    {
        return $this->transition($event, EventModerationStatus::Pending, $actor, array_merge($context, ['transition' => 'cancel']));
    }

    public function reconsider(Event $event, ?Model $actor = null, array $context = []): EventReview
    {
        return $this->transition($event, EventModerationStatus::Pending, $actor, array_merge($context, ['transition' => 'reconsider']));
    }

    public function revertToDraft(Event $event, ?Model $actor = null, array $context = []): EventReview
    {
        return $this->transition($event, EventModerationStatus::Pending, $actor, array_merge($context, ['transition' => 'revert_to_draft']));
    }

    public function remoderate(Event $event, ?Model $actor = null, array $context = []): EventReview
    {
        return $this->transition($event, EventModerationStatus::Pending, $actor, array_merge($context, ['transition' => 'remoderate']));
    }

    private function createSubmission(Event $event, ?Model $actor, array $context): EventSubmission
    {
        $submission = new EventSubmission;
        $submission->event()->associate($event);

        if ($actor !== null) {
            $submission->submittedBy()->associate($actor);
        }

        $submission->fill([
            'status' => EventModerationStatus::Pending->value,
            'submitted_at' => now(),
            'metadata' => $this->buildMetadata(
                $context,
                'submit',
                $event->moderation_status instanceof EventModerationStatus ? $event->moderation_status : EventModerationStatus::Pending,
                EventModerationStatus::Pending,
                $actor,
                $this->contextString($context, ['note', 'notes']),
                $this->contextString($context, ['reason_key', 'reason']),
            ),
        ]);
        $submission->save();

        return $submission;
    }

    private function assertTransitionIsAllowed(
        string $actionKey,
        EventModerationStatus $fromStatus,
        EventModerationStatus $decision,
        ?string $note,
        ?string $reasonKey,
    ): void {
        if (! EventModerationPolicy::canTransition($actionKey, $fromStatus, $decision)) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] moderation transition from [%s] to [%s] is not allowed.',
                $actionKey,
                $fromStatus->value,
                $decision->value,
            ));
        }

        if (EventModerationPolicy::noteRequired($actionKey) && $note === null) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] moderation transition requires a note.',
                $actionKey,
            ));
        }

        if (EventModerationPolicy::reasonRequired($actionKey) && $reasonKey === null) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] moderation transition requires a reason code.',
                $actionKey,
            ));
        }

        if ($reasonKey === null) {
            return;
        }

        if (! EventModerationPolicy::hasReasonCode($reasonKey)) {
            throw new InvalidArgumentException(sprintf(
                'The moderation reason code [%s] is not configured.',
                $reasonKey,
            ));
        }

        $configuredReason = config('events.moderation.reason_codes.' . $reasonKey);

        if (! is_array($configuredReason)) {
            return;
        }

        $reasonRequiresNote = (bool) ($configuredReason['note_required'] ?? false);

        if ($reasonRequiresNote && $note === null) {
            throw new InvalidArgumentException(sprintf(
                'The moderation reason code [%s] requires a note.',
                $reasonKey,
            ));
        }
    }

    private function actionKey(array $context, EventModerationStatus $decision): string
    {
        $action = $this->contextString($context, ['transition', 'action']);

        return $action ?? EventModerationPolicy::actionKeyForDecision($decision);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function buildMetadata(
        array $context,
        string $actionKey,
        EventModerationStatus $fromStatus,
        EventModerationStatus $toStatus,
        ?Model $actor,
        ?string $note,
        ?string $reasonKey,
    ): ?array {
        $metadata = array_merge($context, [
            'action' => $actionKey,
            'from_status' => $fromStatus->value,
            'to_status' => $toStatus->value,
            'note' => $note,
            'reason_key' => $reasonKey,
        ]);

        if ($actor !== null) {
            $metadata['actor_type'] = $actor->getMorphClass();
            $metadata['actor_id'] = (string) $actor->getKey();
        }

        $metadata = array_filter($metadata, static function (mixed $value): bool {
            return match (true) {
                $value === null => false,
                is_string($value) => mb_trim($value) !== '',
                is_array($value) => $value !== [],
                default => true,
            };
        });

        return $metadata !== [] ? $metadata : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function afterSnapshot(Event $event, EventModerationStatus $decision): array
    {
        return array_merge($event->toArray(), [
            'moderation_status' => $decision->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function contextString(array $context, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($context, $key);

            if (! is_string($value)) {
                continue;
            }

            $value = mb_trim($value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
