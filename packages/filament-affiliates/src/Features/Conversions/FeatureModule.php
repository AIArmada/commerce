<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Conversions;

use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\FilamentAffiliates\Features\Conversions\EntryPoints\Filament\Policies\AffiliateConversionPolicy;
use AIArmada\FilamentAffiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void {}

    public function boot(Application $app): void
    {
        Gate::policy(AffiliateConversion::class, AffiliateConversionPolicy::class);
    }
}
