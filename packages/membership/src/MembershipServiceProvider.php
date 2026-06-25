<?php

declare(strict_types=1);

namespace AIArmada\Membership;

use AIArmada\Membership\Console\Commands\MakePivotCommand;
use AIArmada\Membership\Console\Commands\SyncRolesCommand;
use AIArmada\Membership\Services\MembershipRoleSyncService;
use Illuminate\Support\ServiceProvider;

final class MembershipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/membership.php', 'membership');

        $this->app->singleton(MembershipRoleSyncService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/membership.php' => config_path('membership.php'),
        ], 'membership-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncRolesCommand::class,
                MakePivotCommand::class,
            ]);
        }
    }
}
