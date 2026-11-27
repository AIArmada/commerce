<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Events\FeedbackTestimonialRejected;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use Carbon\CarbonImmutable;

final readonly class RejectFeedbackTestimonialAction
{
    public function execute(FeedbackTestimonial $testimonial): FeedbackTestimonial
    {
        return $testimonial->getConnection()->transaction(function () use ($testimonial): FeedbackTestimonial {
            if (FeedbackTestimonial::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackTestimonial::class, $testimonial->id);
            }

            $testimonial = FeedbackTestimonial::query()->whereKey($testimonial->id)->lockForUpdate()->firstOrFail();

            if ($testimonial->status === FeedbackTestimonialStatus::Rejected) {
                return $testimonial;
            }

            $testimonial->forceFill([
                'status' => FeedbackTestimonialStatus::Rejected,
                'rejected_at' => CarbonImmutable::now(),
            ])->save();

            FeedbackTestimonialRejected::dispatch($testimonial);

            return $testimonial;
        });
    }
}
