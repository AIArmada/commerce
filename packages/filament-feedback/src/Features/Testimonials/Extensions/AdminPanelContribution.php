<?php

declare(strict_types=1);

namespace AIArmada\FilamentFeedback\Features\Testimonials\Extensions;

use AIArmada\FilamentFeedback\Contracts\AdminPanelContribution as ContributionContract;
use AIArmada\FilamentFeedback\Features\Testimonials\EntryPoints\Filament\Resources\FeedbackTestimonialResource;

final class AdminPanelContribution implements ContributionContract
{
    public function resources(): array
    {
        return array_filter([
            4 => config('filament-feedback.resources.enabled.feedback_testimonial', true) ? FeedbackTestimonialResource::class : null,
        ], static fn (?string $class): bool => $class !== null);
    }

    public function widgets(): array
    {
        return [];
    }

    public function pages(): array
    {
        return [];
    }
}
