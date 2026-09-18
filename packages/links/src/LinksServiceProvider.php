<?php

declare(strict_types=1);

namespace AIArmada\Links;

use AIArmada\Links\Console\Commands\PruneLinkClicksCommand;
use AIArmada\Links\Contracts\BotDetectorInterface;
use AIArmada\Links\Contracts\SlugGeneratorInterface;
use AIArmada\Links\Contracts\UserAgentParserInterface;
use AIArmada\Links\Support\DefaultBotDetector;
use AIArmada\Links\Support\DefaultSlugGenerator;
use AIArmada\Links\Support\DeviceDetectorUserAgentParser;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LinksServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('links')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasRoutes(['web'])
            ->hasCommand(PruneLinkClicksCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(SlugGeneratorInterface::class, DefaultSlugGenerator::class);
        $this->app->bind(BotDetectorInterface::class, DefaultBotDetector::class);
        $this->app->bind(UserAgentParserInterface::class, DeviceDetectorUserAgentParser::class);
    }
}
