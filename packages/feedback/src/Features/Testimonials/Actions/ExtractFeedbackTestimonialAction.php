<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Actions;

use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Events\FeedbackTestimonialExtracted;
use AIArmada\Feedback\Features\Responses\Api\ReadsTestimonialSource;
use AIArmada\Feedback\Features\Testimonials\Api\ExtractsTestimonials;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;

final readonly class ExtractFeedbackTestimonialAction implements ExtractsTestimonials
{
    public function __construct(private ReadsTestimonialSource $sources) {}

    public function execute(string $responseId): ?string
    {
        return (new FeedbackTestimonial)->getConnection()->transaction(function () use ($responseId): ?string {
            $source = $this->sources->lockForExtraction($responseId);

            if ($source === null) {
                return null;
            }

            $existing = FeedbackTestimonial::query()->where('feedback_response_id', $source->responseId)->first();

            if ($existing !== null) {
                return $existing->id;
            }

            $testimonial = FeedbackTestimonial::create([
                'feedback_response_id' => $source->responseId,
                'feedback_answer_id' => $source->answerId,
                'subject_type' => $source->subjectType,
                'subject_id' => $source->subjectId,
                'respondent_type' => $source->respondentType,
                'respondent_id' => $source->respondentId,
                'quote' => $source->quote,
                'status' => FeedbackTestimonialStatus::Pending,
            ]);

            FeedbackTestimonialExtracted::dispatch($testimonial);

            return $testimonial->id;
        });
    }
}
