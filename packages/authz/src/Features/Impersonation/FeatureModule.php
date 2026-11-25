<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Impersonation;

use AIArmada\Authz\Features\Impersonation\Actions\ImpersonateManager;
use AIArmada\Authz\Features\Impersonation\Api\ImpersonationApi;
use AIArmada\Authz\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use AIArmada\Authz\Features\Impersonation\EntryPoints\Http\LeaveImpersonationController;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->scoped(ImpersonateManager::class);
        $app->scoped(ImpersonationApi::class, static fn (Application $app): ImpersonationApi => $app->make(ImpersonateManager::class));
    }

    public function boot(Application $app): void
    {
        if ($app->routesAreCached()) {
            return;
        }

        Route::post('authz/leave-impersonation', LeaveImpersonationController::class)
            ->middleware(['web', 'auth'])
            ->name('authz.leave-impersonation');
    }
}
