<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Events\FeedbackTestimonialPublished;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Carbon\CarbonImmutable;
use RuntimeException;

final class PublishFeedbackTestimonialAction
{
    public function execute(FeedbackTestimonial $testimonial): FeedbackTestimonial
    {
        if ($testimonial->status === FeedbackTestimonialStatus::Published) {
            return $testimonial;
        }

        if ($testimonial->status !== FeedbackTestimonialStatus::Approved) {
            throw new RuntimeException('Only approved testimonials can be published.');
        }

        if ($testimonial->permission_given_at === null) {
            throw new RuntimeException('Permission has not been given for this testimonial.');
        }

        $testimonial->forceFill([
            'status' => FeedbackTestimonialStatus::Published,
            'published_at' => CarbonImmutable::now(),
        ])->save();

        FeedbackTestimonialPublished::dispatch($testimonial);

        return $testimonial;
    }
}
