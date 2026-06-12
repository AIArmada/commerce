<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Events\FeedbackTestimonialExtracted;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Models\FeedbackTestimonial;

final class ExtractFeedbackTestimonialAction
{
    public function execute(FeedbackResponse $response): ?FeedbackTestimonial
    {
        $form = $response->form;

        if ($form->purpose !== 'testimonial_collection') {
            return null;
        }

        $textAnswer = FeedbackAnswer::where('feedback_response_id', $response->id)
            ->whereNotNull('text_value')
            ->where('text_value', '!=', '')
            ->first();

        if ($textAnswer === null) {
            return null;
        }

        $existing = FeedbackTestimonial::where('feedback_response_id', $response->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $testimonial = FeedbackTestimonial::create([
            'feedback_response_id' => $response->id,
            'feedback_answer_id' => $textAnswer->id,
            'subject_type' => $response->subject_type,
            'subject_id' => $response->subject_id,
            'respondent_type' => $response->is_anonymous ? null : $response->respondent_type,
            'respondent_id' => $response->is_anonymous ? null : $response->respondent_id,
            'quote' => $textAnswer->text_value,
            'status' => FeedbackTestimonialStatus::Pending,
        ]);

        FeedbackTestimonialExtracted::dispatch($testimonial);

        return $testimonial;
    }
}
