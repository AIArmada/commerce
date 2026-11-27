<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Actions;

use AIArmada\Feedback\Data\CreateFeedbackFormData;
use AIArmada\Feedback\Events\FeedbackFormCreated;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Support\FeedbackModelReferenceGuard;

final class CreateFeedbackFormAction
{
    public function __construct(
        private readonly FeedbackModelReferenceGuard $referenceGuard,
    ) {}

    public function execute(CreateFeedbackFormData $data): FeedbackForm
    {
        $subject = $this->referenceGuard->resolve($data->subjectType, $data->subjectId);

        $form = FeedbackForm::create([
            'name' => $data->name,
            'slug' => $data->slug,
            'purpose' => $data->purpose,
            'status' => $data->status,
            'visibility' => $data->visibility,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
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

        FeedbackFormCreated::dispatch($form->id, $form->owner_type, $form->owner_id);

        return $form;
    }
}
