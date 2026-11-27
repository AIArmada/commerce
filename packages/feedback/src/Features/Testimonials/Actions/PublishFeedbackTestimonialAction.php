<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Feedback\Enums\FeedbackTestimonialStatus;
use AIArmada\Feedback\Events\FeedbackTestimonialPublished;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class PublishFeedbackTestimonialAction
{
    public function execute(FeedbackTestimonial $testimonial): FeedbackTestimonial
    {
        return $testimonial->getConnection()->transaction(function () use ($testimonial): FeedbackTestimonial {
            if (FeedbackTestimonial::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(FeedbackTestimonial::class, $testimonial->id);
            }

            $testimonial = FeedbackTestimonial::query()->whereKey($testimonial->id)->lockForUpdate()->firstOrFail();

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
                'hidden_at' => null,
            ])->save();

            FeedbackTestimonialPublished::dispatch($testimonial);

            return $testimonial;
        });
    }
}
