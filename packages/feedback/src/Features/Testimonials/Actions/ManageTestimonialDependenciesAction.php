<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Actions;

use AIArmada\Feedback\Features\Testimonials\Api\ManagesTestimonialDependencies;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;

final readonly class ManageTestimonialDependenciesAction implements ManagesTestimonialDependencies
{
    public function deleteForResponse(string $responseId): void
    {
        FeedbackTestimonial::query()->where('feedback_response_id', $responseId)
            ->chunkById(200, fn ($records) => $records->each->delete());
    }

    public function deleteForAnswer(string $answerId): void
    {
        FeedbackTestimonial::query()->where('feedback_answer_id', $answerId)
            ->chunkById(200, fn ($records) => $records->each->delete());
    }

    public function detachForAnswers(array $answerIds): void
    {
        FeedbackTestimonial::query()->whereIn('feedback_answer_id', $answerIds)
            ->update(['feedback_answer_id' => null]);
    }
}
