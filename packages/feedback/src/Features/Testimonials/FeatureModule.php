<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Testimonials;

use AIArmada\Feedback\Features\Testimonials\Actions\ExtractFeedbackTestimonialAction;
use AIArmada\Feedback\Features\Testimonials\Actions\ManageTestimonialDependenciesAction;
use AIArmada\Feedback\Features\Testimonials\Api\ExtractsTestimonials;
use AIArmada\Feedback\Features\Testimonials\Api\ManagesTestimonialDependencies;
use AIArmada\Feedback\Features\Testimonials\Models\FeedbackTestimonial;
use AIArmada\Feedback\Features\Testimonials\Policies\FeedbackTestimonialPolicy;
use AIArmada\Feedback\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(ExtractsTestimonials::class, ExtractFeedbackTestimonialAction::class);
        $app->bind(ManagesTestimonialDependencies::class, ManageTestimonialDependenciesAction::class);
        Gate::policy(FeedbackTestimonial::class, FeedbackTestimonialPolicy::class);
    }

    public function boot(Application $app): void {}
}
