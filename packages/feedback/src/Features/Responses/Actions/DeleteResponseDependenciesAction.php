<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\Feedback\Features\Forms\Api\ReadsOwnedFormIdentity;
use AIArmada\Feedback\Features\Responses\Api\DeletesResponseDependencies;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;

final readonly class DeleteResponseDependenciesAction implements DeletesResponseDependencies
{
    public function __construct(private ReadsOwnedFormIdentity $forms) {}

    public function deleteForForm(string $formId): void
    {
        $this->forms->requireOwned($formId);

        (new FeedbackResponse)->getConnection()->transaction(function () use ($formId): void {
            FeedbackAnswer::query()->whereHas('response', fn ($query) => $query->where('feedback_form_id', $formId))->delete();
            FeedbackResponse::query()->where('feedback_form_id', $formId)
                ->chunkById(200, fn ($records) => $records->each->delete());
        });
    }

    public function deleteAnswersForQuestion(string $questionId): void
    {
        $this->forms->requireOwnedQuestion($questionId);

        FeedbackAnswer::query()->where('feedback_question_id', $questionId)->delete();
    }
}
