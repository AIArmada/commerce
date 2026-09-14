<?php

declare(strict_types=1);

namespace AIArmada\Contacting;

use AIArmada\Contacting\Actions\NormalizeContactMethodAction;
use AIArmada\Contacting\Actions\NormalizeSocialProfileAction;
use AIArmada\Contacting\Contracts\ContactMethodNormalizer;
use AIArmada\Contacting\Contracts\SocialProfileNormalizer;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ContactingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('contacting')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(ContactMethodNormalizer::class, NormalizeContactMethodAction::class);
        $this->app->bind(SocialProfileNormalizer::class, NormalizeSocialProfileAction::class);
    }
}
