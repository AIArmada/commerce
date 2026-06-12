<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use Illuminate\Database\Eloquent\Model;

final class FeedbackTestimonialPolicy
{
    public function viewAny(Model $user): bool
    {
        return OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal();
    }

    public function view(Model $user, FeedbackTestimonial $testimonial): bool
    {
        return $this->ownerMatches($testimonial);
    }

    public function approve(Model $user, FeedbackTestimonial $testimonial): bool
    {
        return $this->ownerMatches($testimonial);
    }

    public function reject(Model $user, FeedbackTestimonial $testimonial): bool
    {
        return $this->ownerMatches($testimonial);
    }

    public function publish(Model $user, FeedbackTestimonial $testimonial): bool
    {
        return $this->ownerMatches($testimonial);
    }

    public function hide(Model $user, FeedbackTestimonial $testimonial): bool
    {
        return $this->ownerMatches($testimonial);
    }

    private function ownerMatches(FeedbackTestimonial $testimonial): bool
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $testimonial->isGlobal();
        }

        return $testimonial->belongsToOwner($owner);
    }
}
