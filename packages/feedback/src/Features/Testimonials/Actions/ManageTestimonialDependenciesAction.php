<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Actions;

use AIArmada\Feedback\Features\Testimonials\Api\ManagesTestimonialDependencies;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use Illuminate\Database\Eloquent\Builder;

final readonly class ManageTestimonialDependenciesAction implements ManagesTestimonialDependencies
{
    public function deleteForForm(string $formId): void
    {
        FeedbackTestimonial::query()
            ->where(function (Builder $query) use ($formId): void {
                $query->whereHas('response', fn (Builder $response) => $response->where('feedback_form_id', $formId))
                    ->orWhereHas('answer.response', fn (Builder $response) => $response->where('feedback_form_id', $formId));
            })->chunkById(200, fn ($records) => $records->each->delete());
    }

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

    public function detachForQuestion(string $questionId): void
    {
        FeedbackTestimonial::query()->whereHas('answer', fn (Builder $query) => $query->where('feedback_question_id', $questionId))
            ->update(['feedback_answer_id' => null]);
    }
}
