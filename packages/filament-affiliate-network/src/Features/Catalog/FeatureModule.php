<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Catalog;

use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Policies\AffiliateOfferCategoryPolicy;
use AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Policies\AffiliateOfferPolicy;
use AIArmada\FilamentAffiliateNetwork\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void {}

    public function boot(Application $app): void
    {
        Gate::policy(AffiliateOffer::class, AffiliateOfferPolicy::class);
        Gate::policy(AffiliateOfferCategory::class, AffiliateOfferCategoryPolicy::class);
    }
}
