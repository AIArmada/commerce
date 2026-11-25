<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Attribution;

use AIArmada\Affiliates\Features\Attribution\Models\AffiliateLink;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateTouchpoint;
use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Policies\AffiliateLinkPolicy;
use AIArmada\FilamentAffiliates\Features\Attribution\EntryPoints\Filament\Policies\AffiliateTouchpointPolicy;
use AIArmada\FilamentAffiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void {}

    public function boot(Application $app): void
    {
        Gate::policy(AffiliateLink::class, AffiliateLinkPolicy::class);
        Gate::policy(AffiliateTouchpoint::class, AffiliateTouchpointPolicy::class);
    }
}
