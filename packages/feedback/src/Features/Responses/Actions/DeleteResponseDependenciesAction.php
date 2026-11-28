<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Actions;

use AIArmada\Contacting\Features\ContactMethods\Actions\DeleteContactMethodsForEntityAction;
use AIArmada\Contacting\Features\SocialProfiles\Actions\DeleteSocialProfilesForEntityAction;
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
        private DeleteContactMethodsForEntityAction $contactMethods,
        private DeleteSocialProfilesForEntityAction $socialProfiles,
    ) {}

    public function deleteForForm(string $formId): void
    {
        $this->forms->requireOwned($formId);

        (new FeedbackResponse)->getConnection()->transaction(function () use ($formId): void {
            FeedbackResponse::query()->where('feedback_form_id', $formId)
                ->select('id')
                ->chunkById(200, function ($records): void {
                    $responseIds = $records->pluck('id')->all();

                    if ($responseIds === []) {
                        return;
                    }

                    $answerIds = FeedbackAnswer::query()
                        ->whereIn('feedback_response_id', $responseIds)
                        ->pluck('id')
                        ->all();

                    $this->testimonials->deleteForResponses($responseIds, $answerIds);

                    $morphClass = (new FeedbackResponse)->getMorphClass();
                    $this->contactMethods->executeForEntities($morphClass, $responseIds);
                    $this->socialProfiles->executeForEntities($morphClass, $responseIds);

                    FeedbackAnswer::query()->whereIn('feedback_response_id', $responseIds)->delete();
                    FeedbackResponse::query()->whereIn('id', $responseIds)->delete();
                });
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
