<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Queries;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Api\QuestionDefinition;
use AIArmada\Feedback\Features\Forms\Api\ReadsSubmissionForm;
use AIArmada\Feedback\Features\Forms\Api\SubmissionFormDefinition;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;

final readonly class ReadSubmissionFormQuery implements ReadsSubmissionForm
{
    public function requireOwned(string $formId, bool $includeQuestions = true): SubmissionFormDefinition
    {
        if (FeedbackForm::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $formId);
        }

        $query = FeedbackForm::query()->whereKey($formId)->lockForUpdate();

        if ($includeQuestions) {
            $query->with('questions.options');
        }

        $form = $query->firstOrFail();

        return new SubmissionFormDefinition(
            id: $form->id,
            status: $form->status->value,
            opensAt: $form->opens_at?->toIso8601String(),
            closesAt: $form->closes_at?->toIso8601String(),
            isAnonymousAllowed: $form->is_anonymous_allowed,
            isLoginRequired: $form->is_login_required,
            isOneResponsePerRespondent: $form->is_one_response_per_respondent,
            subjectType: $form->subject_type,
            subjectId: $form->subject_id,
            questions: $includeQuestions
                ? $form->questions->map(static fn (FeedbackQuestion $question): QuestionDefinition => $question->definition())->values()->all()
                : [],
        );
    }
}
