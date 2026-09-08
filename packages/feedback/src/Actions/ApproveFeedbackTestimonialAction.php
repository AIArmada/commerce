<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Events\FeedbackTestimonialApproved;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Carbon\CarbonImmutable;

final class ApproveFeedbackTestimonialAction
{
    public function execute(FeedbackTestimonial $testimonial): FeedbackTestimonial
    {
        $testimonial = OwnerWriteGuard::findOrFailForOwner(FeedbackTestimonial::class, $testimonial->id);

        if ($testimonial->status === FeedbackTestimonialStatus::Approved) {
            return $testimonial;
        }

        if ($testimonial->status === FeedbackTestimonialStatus::Rejected) {
            return $testimonial;
        }

        $testimonial->forceFill([
            'status' => FeedbackTestimonialStatus::Approved,
            'approved_at' => CarbonImmutable::now(),
        ])->save();

        FeedbackTestimonialApproved::dispatch($testimonial);

        return $testimonial;
    }
}
