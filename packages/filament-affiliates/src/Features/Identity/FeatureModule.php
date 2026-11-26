<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Identity;

use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\FilamentAffiliates\Features\Identity\EntryPoints\Filament\Policies\AffiliatePolicy;
use AIArmada\FilamentAffiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(Api\AffiliateAdminLinks::class, Queries\AffiliateResourceLinks::class);
    }

    public function boot(Application $app): void
    {
        Gate::policy(Affiliate::class, AffiliatePolicy::class);
    }
}
