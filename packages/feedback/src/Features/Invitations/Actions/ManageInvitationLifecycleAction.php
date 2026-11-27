<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Invitations\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Exceptions\FeedbackInvitationExpiredException;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Invitations\Api\ManagesInvitationLifecycle;
use AIArmada\Feedback\Features\Invitations\Models\FeedbackInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

final readonly class ManageInvitationLifecycleAction implements ManagesInvitationLifecycle
{
    public function __construct(private ExpireFeedbackInvitationAction $expireInvitation) {}

    public function assertUsable(string $invitationId, string $formId, ?string $respondentType, ?string $respondentId): void
    {
        if (FeedbackInvitation::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackInvitation::class, $invitationId);
        }

        $invitation = FeedbackInvitation::query()->whereKey($invitationId)->lockForUpdate()->firstOrFail();

        if ($invitation->feedback_form_id !== $formId) {
            throw new RuntimeException('This invitation does not belong to the selected form.');
        }

        if ($invitation->status === FeedbackInvitationStatus::Cancelled) {
            throw new RuntimeException('This invitation has been cancelled.');
        }

        if ($invitation->status === FeedbackInvitationStatus::Submitted) {
            throw new RuntimeException('This invitation has already been used.');
        }

        if ($invitation->status === FeedbackInvitationStatus::Expired || $invitation->isExpired()) {
            throw new FeedbackInvitationExpiredException($invitationId);
        }

        if (($invitation->recipient_type !== null || $invitation->recipient_id !== null)
            && ($invitation->recipient_type !== $respondentType || (string) $invitation->recipient_id !== $respondentId)) {
            throw new AuthorizationException('This invitation belongs to a different recipient.');
        }
    }

    public function start(string $invitationId): void
    {
        $this->transition($invitationId, FeedbackInvitationStatus::Started);
    }

    public function submit(string $invitationId): void
    {
        $this->transition($invitationId, FeedbackInvitationStatus::Submitted);
    }

    public function expire(string $invitationId): void
    {
        $this->expireInvitation->execute($invitationId);
    }

    public function deleteForForm(string $formId): void
    {
        if (FeedbackForm::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $formId);
        }

        FeedbackInvitation::query()->where('feedback_form_id', $formId)->delete();
    }

    private function transition(string $invitationId, FeedbackInvitationStatus $status): void
    {
        (new FeedbackInvitation)->getConnection()->transaction(function () use ($invitationId, $status): void {
            if (FeedbackInvitation::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackInvitation::class, $invitationId);
            }

            $invitation = FeedbackInvitation::query()->whereKey($invitationId)->lockForUpdate()->firstOrFail();

            if ($invitation->isExpired() || $invitation->status === FeedbackInvitationStatus::Expired) {
                throw new FeedbackInvitationExpiredException($invitationId);
            }

            if (in_array($invitation->status, [
                FeedbackInvitationStatus::Cancelled,
                FeedbackInvitationStatus::Expired,
                FeedbackInvitationStatus::Submitted,
            ], true)) {
                throw new RuntimeException('This invitation cannot be used.');
            }

            if ($invitation->status === $status) {
                return;
            }

            $invitation->forceFill([
                'status' => $status,
                $status === FeedbackInvitationStatus::Started ? 'started_at' : 'submitted_at' => CarbonImmutable::now(),
            ])->save();
        });
    }
}
