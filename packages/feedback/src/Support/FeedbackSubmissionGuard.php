<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Support;

use AIArmada\Feedback\Enums\FeedbackFormStatus;
use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Exceptions\FeedbackInvitationExpiredException;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use Carbon\CarbonImmutable;
use RuntimeException;

final class FeedbackSubmissionGuard
{
    public function assertFormAcceptingSubmissions(
        FeedbackForm $form,
        bool $isAnonymous,
        ?string $respondentType,
        ?string $respondentId,
    ): void {
        if ($form->status !== FeedbackFormStatus::Published) {
            throw new RuntimeException('This form is not accepting submissions.');
        }

        if ($form->opens_at !== null && CarbonImmutable::now()->isBefore($form->opens_at)) {
            throw new RuntimeException('This form has not opened yet.');
        }

        if ($form->closes_at !== null && CarbonImmutable::now()->isAfter($form->closes_at)) {
            throw new RuntimeException('This form has closed.');
        }

        if ($isAnonymous && ! $form->is_anonymous_allowed) {
            throw new RuntimeException('Anonymous submissions are not allowed for this form.');
        }

        if ($form->is_login_required && ($respondentType === null || $respondentId === null)) {
            throw new RuntimeException('You must be logged in to submit this form.');
        }
    }

    public function assertInvitationValid(FeedbackInvitation $invitation, FeedbackForm $form): void
    {
        if ($invitation->feedback_form_id !== $form->id) {
            throw new RuntimeException('This invitation does not belong to the selected form.');
        }

        if ($invitation->status === FeedbackInvitationStatus::Expired) {
            throw new FeedbackInvitationExpiredException($invitation->id);
        }

        if ($invitation->status === FeedbackInvitationStatus::Cancelled) {
            throw new RuntimeException('This invitation has been cancelled.');
        }

        if ($invitation->status === FeedbackInvitationStatus::Submitted) {
            throw new RuntimeException('This invitation has already been used.');
        }

        if ($invitation->expires_at !== null && CarbonImmutable::now()->isAfter($invitation->expires_at)) {
            throw new FeedbackInvitationExpiredException($invitation->id);
        }
    }
}
