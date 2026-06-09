<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt;

use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Models\JntWebhookLog;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentJntServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-jnt')
            ->hasConfigFile()
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentJntPlugin::class);
    }

    public function packageBooted(): void
    {
        Gate::policy(JntOrder::class, Policies\JntOrderPolicy::class);
        Gate::policy(JntTrackingEvent::class, Policies\JntTrackingEventPolicy::class);
        Gate::policy(JntWebhookLog::class, Policies\JntWebhookLogPolicy::class);
    }
}
