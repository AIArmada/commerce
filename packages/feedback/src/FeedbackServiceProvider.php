<?php

declare(strict_types=1);

namespace AIArmada\Feedback;

use AIArmada\Feedback\Contracts\AnswerNormalizer;
use AIArmada\Feedback\Contracts\InvitationUrlGenerator;
use AIArmada\Feedback\Models\FeedbackForm;
use AIArmada\Feedback\Models\FeedbackResponse;
use AIArmada\Feedback\Models\FeedbackTemplate;
use AIArmada\Feedback\Models\FeedbackTestimonial;
use AIArmada\Feedback\Platform\ExtensionRegistry;
use AIArmada\Feedback\Platform\FeatureCatalog;
use AIArmada\Feedback\Platform\FeatureModule;
use AIArmada\Feedback\Policies\FeedbackFormPolicy;
use AIArmada\Feedback\Policies\FeedbackResponsePolicy;
use AIArmada\Feedback\Policies\FeedbackTemplatePolicy;
use AIArmada\Feedback\Policies\FeedbackTestimonialPolicy;
use AIArmada\Feedback\Support\AnswerValueNormalizer;
use AIArmada\Feedback\Drivers\InvitationUrlGenerator as DefaultInvitationUrlGenerator;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FeedbackServiceProvider extends PackageServiceProvider
{
    /** @var array<string, FeatureModule> */
    private array $featureModules = [];

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
        Gate::policy(FeedbackTemplate::class, FeedbackTemplatePolicy::class);
        Gate::policy(FeedbackTestimonial::class, FeedbackTestimonialPolicy::class);

        $this->app->bind(InvitationUrlGenerator::class, DefaultInvitationUrlGenerator::class);
        $this->app->bind(AnswerNormalizer::class, AnswerValueNormalizer::class);

        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn ($app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $feature) {
            $class = __NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule';
            $module = $this->app->make($class);

            if (! $module instanceof FeatureModule) {
                throw new InvalidArgumentException("Invalid feedback feature module [{$class}].");
            }

            $this->featureModules[$feature] = $module;
            $module->register($this->app);
        }
    }

    public function bootingPackage(): void
    {
        foreach ($this->featureModules as $module) {
            $module->boot($this->app);
        }
    }
}
