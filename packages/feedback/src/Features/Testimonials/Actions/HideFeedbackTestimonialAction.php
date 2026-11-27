<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Events\FeedbackTestimonialHidden;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use Carbon\CarbonImmutable;

final readonly class HideFeedbackTestimonialAction
{
    public function execute(FeedbackTestimonial $testimonial): FeedbackTestimonial
    {
        return $testimonial->getConnection()->transaction(function () use ($testimonial): FeedbackTestimonial {
            if (FeedbackTestimonial::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackTestimonial::class, $testimonial->id);
            }

            $testimonial = FeedbackTestimonial::query()->whereKey($testimonial->id)->lockForUpdate()->firstOrFail();

            if ($testimonial->status !== FeedbackTestimonialStatus::Published) {
                return $testimonial;
            }

            $testimonial->forceFill([
                'status' => FeedbackTestimonialStatus::Hidden,
                'hidden_at' => CarbonImmutable::now(),
            ])->save();

            FeedbackTestimonialHidden::dispatch($testimonial);

            return $testimonial;
        });
    }
}
