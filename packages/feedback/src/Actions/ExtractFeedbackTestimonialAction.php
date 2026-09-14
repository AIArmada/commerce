<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackFormPurpose;
use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Events\FeedbackTestimonialExtracted;
use AIArmada\Feedback\Models\FeedbackAnswer;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Illuminate\Database\Eloquent\Builder;

final class ExtractFeedbackTestimonialAction
{
    public function execute(FeedbackResponse $response): ?FeedbackTestimonial
    {
        $response = OwnerWriteGuard::findOrFailForOwner(FeedbackResponse::class, $response->id);

        $form = $response->form;

        if (! $form || $form->purpose !== FeedbackFormPurpose::TestimonialCollection->value) {
            return null;
        }

        $textAnswer = FeedbackAnswer::query()
            ->where('feedback_response_id', $response->id)
            ->whereNotNull('text_value')
            ->where('text_value', '!=', '')
            ->whereHas('question', function (Builder $query): void {
                $query->whereIn('type', ['long_text', 'short_text']);
            })
            ->first();

        if ($textAnswer === null) {
            return null;
        }

        $quote = mb_trim(strip_tags((string) $textAnswer->text_value));

        if ($quote === '') {
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
            'quote' => mb_substr($quote, 0, 2000),
            'status' => FeedbackTestimonialStatus::Pending,
        ]);

        FeedbackTestimonialExtracted::dispatch($testimonial);

        return $testimonial;
    }
}
