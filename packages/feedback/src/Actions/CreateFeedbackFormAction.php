<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Events\FeedbackFormCreated;
use AIArmada\Feedback\Models\FeedbackForm;

final class CreateFeedbackFormAction
{
    public function execute(CreateFeedbackFormData $data): FeedbackForm
    {
        $form = FeedbackForm::create([
            'name' => $data->name,
            'slug' => $data->slug,
            'purpose' => $data->purpose,
            'status' => $data->status,
            'visibility' => $data->visibility,
            'subject_type' => $data->subjectType,
            'subject_id' => $data->subjectId,
            'is_anonymous_allowed' => $data->isAnonymousAllowed,
            'is_anonymity_optional' => $data->isAnonymityOptional,
            'is_login_required' => $data->isLoginRequired,
            'is_one_response_per_respondent' => $data->isOneResponsePerRespondent,
            'is_edit_after_submit_allowed' => $data->isEditAfterSubmitAllowed,
            'opens_at' => $data->opensAt,
            'closes_at' => $data->closesAt,
            'settings' => $data->settings,
            'metadata' => $data->metadata,
        ]);

        FeedbackFormCreated::dispatch($form);

        return $form;
    }
}
