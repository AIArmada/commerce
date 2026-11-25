<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Sites;

use AIArmada\FilamentAffiliateNetwork\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Features\Sites\EntryPoints\Filament\Policies\AffiliateSitePolicy;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void {}

    public function boot(Application $app): void
    {
        Gate::policy(AffiliateSite::class, AffiliateSitePolicy::class);
    }
}
