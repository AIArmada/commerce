<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use AIArmada\Feedback\Features\Forms\Models\FeedbackQuestion;
use AIArmada\Feedback\Features\Responses\Api\DeletesResponseDependencies;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;

final readonly class DeleteResponseDependenciesAction implements DeletesResponseDependencies
{
    public function deleteForForm(string $formId): void
    {
        if (FeedbackForm::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackForm::class, $formId);
        }

        (new FeedbackResponse)->getConnection()->transaction(function () use ($formId): void {
            FeedbackAnswer::query()->whereHas('response', fn ($query) => $query->where('feedback_form_id', $formId))->delete();
            FeedbackResponse::query()->where('feedback_form_id', $formId)
                ->chunkById(200, fn ($records) => $records->each->delete());
        });
    }

    public function deleteAnswersForQuestion(string $questionId): void
    {
        if (FeedbackQuestion::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackQuestion::class, $questionId);
        }

        FeedbackAnswer::query()->where('feedback_question_id', $questionId)->delete();
    }
}
