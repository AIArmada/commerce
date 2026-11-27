<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Events\FeedbackTestimonialApproved;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use Carbon\CarbonImmutable;

final readonly class ApproveFeedbackTestimonialAction
{
    public function execute(FeedbackTestimonial $testimonial): FeedbackTestimonial
    {
        return $testimonial->getConnection()->transaction(function () use ($testimonial): FeedbackTestimonial {
            if (FeedbackTestimonial::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackTestimonial::class, $testimonial->id);
            }

            $testimonial = FeedbackTestimonial::query()->whereKey($testimonial->id)->lockForUpdate()->firstOrFail();

            if (! in_array($testimonial->status, [FeedbackTestimonialStatus::Pending, FeedbackTestimonialStatus::Hidden], true)) {
                return $testimonial;
            }

            $testimonial->forceFill([
                'status' => FeedbackTestimonialStatus::Approved,
                'approved_at' => CarbonImmutable::now(),
            ])->save();

            FeedbackTestimonialApproved::dispatch($testimonial->id, $testimonial->owner_type, $testimonial->owner_id);

            return $testimonial;
        });
    }
}
