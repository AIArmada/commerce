<?php

declare(strict_types=1);

namespace AIArmada\Feedback;

use AIArmada\Feedback\Analytics\FeedbackAnalyticsService;
use AIArmada\Feedback\Contracts\AnswerNormalizer;
use AIArmada\Feedback\Contracts\FeedbackAnalyticsCalculator;
use AIArmada\Feedback\Contracts\InvitationUrlGenerator;
use AIArmada\Feedback\Events\FeedbackResponseMarkedSpam;
use AIArmada\Feedback\Events\FeedbackResponseRejected;
use AIArmada\Feedback\Events\FeedbackResponseReviewed;
use AIArmada\Feedback\Events\FeedbackResponseStarted;
use AIArmada\Feedback\Events\FeedbackResponseSubmitted;
use AIArmada\Feedback\Listeners\QueueFeedbackAnalyticsRecalculation;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackInvitation;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Models\FeedbackTemplate;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use AIArmada\Feedback\Policies\FeedbackFormPolicy;
use AIArmada\Feedback\Policies\FeedbackInvitationPolicy;
use AIArmada\Feedback\Policies\FeedbackResponsePolicy;
use AIArmada\Feedback\Policies\FeedbackTemplatePolicy;
use AIArmada\Feedback\Policies\FeedbackTestimonialPolicy;
use AIArmada\Feedback\Support\AnswerValueNormalizer;
use AIArmada\Feedback\Support\InvitationUrlGenerator as DefaultInvitationUrlGenerator;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FeedbackServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('feedback')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function registeringPackage(): void
    {
        Gate::policy(FeedbackForm::class, FeedbackFormPolicy::class);
        Gate::policy(FeedbackResponse::class, FeedbackResponsePolicy::class);
        Gate::policy(FeedbackInvitation::class, FeedbackInvitationPolicy::class);
        Gate::policy(FeedbackTemplate::class, FeedbackTemplatePolicy::class);
        Gate::policy(FeedbackTestimonial::class, FeedbackTestimonialPolicy::class);

        $this->app->bind(InvitationUrlGenerator::class, DefaultInvitationUrlGenerator::class);
        $this->app->bind(AnswerNormalizer::class, AnswerValueNormalizer::class);
        $this->app->bind(FeedbackAnalyticsCalculator::class, FeedbackAnalyticsService::class);
    }

    public function bootingPackage(): void
    {
        foreach ([
            FeedbackResponseStarted::class,
            FeedbackResponseSubmitted::class,
            FeedbackResponseReviewed::class,
            FeedbackResponseRejected::class,
            FeedbackResponseMarkedSpam::class,
        ] as $eventClass) {
            $this->app['events']->listen($eventClass, QueueFeedbackAnalyticsRecalculation::class);
        }
    }
}
