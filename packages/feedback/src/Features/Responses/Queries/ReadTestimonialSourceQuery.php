<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Queries;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackFormPurpose;
use AIArmada\Feedback\Enums\FeedbackResponseStatus;
use AIArmada\Feedback\Features\Forms\Api\ReadsOwnedFormIdentity;
use AIArmada\Feedback\Features\Responses\Api\ReadsTestimonialSource;
use AIArmada\Feedback\Features\Responses\Api\TestimonialSource;
use AIArmada\Feedback\Features\Responses\Models\FeedbackAnswer;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final readonly class ReadTestimonialSourceQuery implements ReadsTestimonialSource
{
    public function __construct(private ReadsOwnedFormIdentity $forms) {}

    public function lockForExtraction(string $responseId): ?TestimonialSource
    {
        if ((new FeedbackResponse)->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Testimonial extraction requires an active feedback transaction.');
        }

        if (FeedbackResponse::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(FeedbackResponse::class, $responseId);
        }

        $response = FeedbackResponse::query()->whereKey($responseId)->lockForUpdate()->firstOrFail();

        if (! in_array($response->status, [FeedbackResponseStatus::Submitted, FeedbackResponseStatus::Reviewed], true)
            || $this->forms->find($response->feedback_form_id)?->purpose !== FeedbackFormPurpose::TestimonialCollection->value) {
            return null;
        }

        $answer = FeedbackAnswer::query()
            ->where('feedback_response_id', $response->id)
            ->whereNotNull('text_value')
            ->where('text_value', '!=', '')
            ->whereHas('question', function (Builder $query) use ($response): void {
                $query->where('feedback_form_id', $response->feedback_form_id)
                    ->whereIn('type', ['long_text', 'short_text']);
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->first(['id', 'text_value']);

        if ($answer === null) {
            return null;
        }

        $quote = mb_trim(strip_tags((string) $answer->text_value));

        if ($quote === '') {
            return null;
        }

        return new TestimonialSource(
            responseId: $response->id,
            answerId: $answer->id,
            subjectType: $response->subject_type,
            subjectId: $response->subject_id,
            respondentType: $response->is_anonymous ? null : $response->respondent_type,
            respondentId: $response->is_anonymous ? null : $response->respondent_id,
            quote: mb_substr($quote, 0, 2000),
        );
    }
}
