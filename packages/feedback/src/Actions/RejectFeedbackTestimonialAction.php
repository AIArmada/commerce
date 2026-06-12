<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Events\FeedbackTestimonialRejected;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Carbon\CarbonImmutable;

final class RejectFeedbackTestimonialAction
{
    public function execute(FeedbackTestimonial $testimonial): FeedbackTestimonial
    {
        if ($testimonial->status === FeedbackTestimonialStatus::Rejected) {
            return $testimonial;
        }

        $testimonial->forceFill([
            'status' => FeedbackTestimonialStatus::Rejected,
            'rejected_at' => CarbonImmutable::now(),
        ])->save();

        FeedbackTestimonialRejected::dispatch($testimonial);

        return $testimonial;
    }
}
