<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Enrollment;

use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\FilamentAffiliateNetwork\Features\Enrollment\EntryPoints\Filament\Policies\AffiliateOfferApplicationPolicy;
use AIArmada\FilamentAffiliateNetwork\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void {}

    public function boot(Application $app): void
    {
        Gate::policy(AffiliateOfferApplication::class, AffiliateOfferApplicationPolicy::class);
    }
}
