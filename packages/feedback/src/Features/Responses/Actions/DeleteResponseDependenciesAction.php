<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\Feedback\Features\Forms\Api\ReadsOwnedFormIdentity;
use AIArmada\Feedback\Features\Responses\Api\DeletesResponseDependencies;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use AIArmada\Feedback\Features\Testimonials\Api\ManagesTestimonialDependencies;

final readonly class DeleteResponseDependenciesAction implements DeletesResponseDependencies
{
    public function __construct(
        private ReadsOwnedFormIdentity $forms,
        private ManagesTestimonialDependencies $testimonials,
    ) {}

    public function deleteForForm(string $formId): void
    {
        $this->forms->requireOwned($formId);

        (new FeedbackResponse)->getConnection()->transaction(function () use ($formId): void {
            FeedbackResponse::query()->where('feedback_form_id', $formId)
                ->chunkById(200, fn ($records) => $records->each->delete());
        });
    }

    public function deleteAnswersForQuestion(string $questionId): void
    {
        $this->forms->requireOwnedQuestion($questionId);

        (new FeedbackAnswer)->getConnection()->transaction(function () use ($questionId): void {
            FeedbackAnswer::query()->where('feedback_question_id', $questionId)
                ->chunkById(200, function ($answers): void {
                    $ids = $answers->pluck('id')->all();
                    $this->testimonials->detachForAnswers($ids);
                    FeedbackAnswer::query()->whereIn('id', $ids)->delete();
                });
        });
    }
}
