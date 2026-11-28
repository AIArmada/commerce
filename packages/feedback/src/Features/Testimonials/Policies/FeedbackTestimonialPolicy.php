<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials\Policies;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use Illuminate\Database\Eloquent\Model;

final class FeedbackTestimonialPolicy
{
    public function viewAny(Model $user): bool
    {
        return $this->canOperate();
    }

    public function export(Model $user): bool
    {
        return $this->canOperate();
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

    private function canOperate(): bool
    {
        $config = FeedbackTestimonial::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        return $this->resolveOwner($config) !== null || OwnerContext::isExplicitGlobal();
    }

    private function ownerMatches(FeedbackTestimonial $testimonial): bool
    {
        $config = FeedbackTestimonial::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        $owner = $this->resolveOwner($config);

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $testimonial->isGlobal();
        }

        return $testimonial->belongsToOwner($owner);
    }

    private function resolveOwner(OwnerScopeConfig $config): ?Model
    {
        return OwnerContext::resolveConfigured($config);
    }
}
