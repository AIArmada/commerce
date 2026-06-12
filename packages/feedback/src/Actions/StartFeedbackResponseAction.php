<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Enums\FeedbackInvitationStatus;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Events\FeedbackResponseStarted;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use AIArmada\Feedback\Models\FeedbackResponse;
use Carbon\CarbonImmutable;

final class StartFeedbackResponseAction
{
    public function execute(
        FeedbackForm $form,
        ?string $respondentType = null,
        ?string $respondentId = null,
        ?FeedbackInvitation $invitation = null,
        bool $isAnonymous = false,
    ): FeedbackResponse {
        $response = FeedbackResponse::create([
            'feedback_form_id' => $form->id,
            'feedback_invitation_id' => $invitation?->id,
            'subject_type' => $form->subject_type,
            'subject_id' => $form->subject_id,
            'respondent_type' => $respondentType,
            'respondent_id' => $respondentId,
            'status' => FeedbackResponseStatus::Draft,
            'is_anonymous' => $isAnonymous,
            'started_at' => CarbonImmutable::now(),
        ]);

        if ($invitation !== null) {
            $invitation->forceFill([
                'status' => FeedbackInvitationStatus::Started,
                'started_at' => CarbonImmutable::now(),
            ])->save();
        }

        FeedbackResponseStarted::dispatch($response);

        return $response;
    }
}
