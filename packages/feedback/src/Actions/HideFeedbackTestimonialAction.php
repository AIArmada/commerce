<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Carbon\CarbonImmutable;

final class HideFeedbackTestimonialAction
{
    public function execute(FeedbackTestimonial $testimonial): FeedbackTestimonial
    {
        if ($testimonial->status === FeedbackTestimonialStatus::Hidden) {
            return $testimonial;
        }

        $testimonial->forceFill([
            'status' => FeedbackTestimonialStatus::Hidden,
            'hidden_at' => CarbonImmutable::now(),
        ])->save();

        return $testimonial;
    }
}
