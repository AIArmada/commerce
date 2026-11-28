<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Actions;

use AIArmada\Contacting\Features\ContactMethods\Actions\DeleteContactMethodsForEntityAction;
use AIArmada\Contacting\Features\SocialProfiles\Actions\DeleteSocialProfilesForEntityAction;
use AIArmada\Feedback\Features\Testimonials\Api\ManagesTestimonialDependencies;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use Illuminate\Database\Eloquent\Builder;

final readonly class ManageTestimonialDependenciesAction implements ManagesTestimonialDependencies
{
    public function __construct(
        private DeleteContactMethodsForEntityAction $contactMethods,
        private DeleteSocialProfilesForEntityAction $socialProfiles,
    ) {}

    public function deleteForResponse(string $responseId): void
    {
        $this->deleteForResponses([$responseId], []);
    }

    public function deleteForAnswer(string $answerId): void
    {
        $this->deleteForResponses([], [$answerId]);
    }

    public function deleteForResponses(array $responseIds, array $answerIds): void
    {
        if ($responseIds === [] && $answerIds === []) {
            return;
        }

        foreach (array_chunk($responseIds, 200) as $chunk) {
            $this->deleteTestimonials(
                FeedbackTestimonial::query()->whereIn('feedback_response_id', $chunk),
            );
        }

        foreach (array_chunk($answerIds, 200) as $chunk) {
            $this->deleteTestimonials(
                FeedbackTestimonial::query()->whereIn('feedback_answer_id', $chunk),
            );
        }
    }

    public function detachForAnswers(array $answerIds): void
    {
        FeedbackTestimonial::query()->whereIn('feedback_answer_id', $answerIds)
            ->update(['feedback_answer_id' => null]);
    }

    /**
     * @param  Builder<FeedbackTestimonial>  $query
     */
    private function deleteTestimonials(Builder $query): void
    {
        $testimonials = (clone $query)->get(['id']);

        if ($testimonials->isEmpty()) {
            return;
        }

        $testimonialIds = $testimonials->modelKeys();
        $morphClass = (new FeedbackTestimonial)->getMorphClass();

        $this->contactMethods->executeForEntities($morphClass, $testimonialIds);
        $this->socialProfiles->executeForEntities($morphClass, $testimonialIds);

        FeedbackTestimonial::query()->whereKey($testimonialIds)->delete();
    }
}
