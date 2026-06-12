<?php

declare(strict_types=1);

namespace AIArmada\Events\Support\Policy;

use AIArmada\Events\Models\EventSubmission;
use InvalidArgumentException;

final class EventModerationPolicy
{
    /** @var array<string, array{from: string[], to: string, note_required?: bool, reason_required?: bool}> */
    private array $actions;

    public function __construct()
    {
        $this->actions = config('events.moderation.actions', []);
    }

    public function canSubmit(EventSubmission $submission): bool
    {
        return $this->isTransitionAllowed('submit', $submission->status);
    }

    public function canApprove(EventSubmission $submission): bool
    {
        return $this->isTransitionAllowed('approve', $submission->status);
    }

    public function canReject(EventSubmission $submission): bool
    {
        return $this->isTransitionAllowed('reject', $submission->status);
    }

    public function canRequestChanges(EventSubmission $submission): bool
    {
        return $this->isTransitionAllowed('request_changes', $submission->status);
    }

    public function canCancel(EventSubmission $submission): bool
    {
        return $this->isTransitionAllowed('cancel', $submission->status);
    }

    public function canReconsider(EventSubmission $submission): bool
    {
        return $this->isTransitionAllowed('reconsider', $submission->status);
    }

    public function isNoteRequired(string $action): bool
    {
        return $this->actions[$action]['note_required'] ?? false;
    }

    public function isReasonRequired(string $action): bool
    {
        return $this->actions[$action]['reason_required'] ?? false;
    }

    public function assertTransitionAllowed(string $action, string $currentStatus): void
    {
        if (! $this->isTransitionAllowed($action, $currentStatus)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot perform "%s" on submission with status "%s".',
                $action,
                $currentStatus,
            ));
        }
    }

    private function isTransitionAllowed(string $action, string $currentStatus): bool
    {
        $config = $this->actions[$action] ?? null;

        if ($config === null) {
            return false;
        }

        return in_array($currentStatus, $config['from'], true);
    }
}
