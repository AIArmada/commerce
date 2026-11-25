<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Commissions;

use AIArmada\Affiliates\Models\AffiliateCommissionTemplate;
use AIArmada\FilamentAffiliates\Features\Commissions\EntryPoints\Filament\Policies\AffiliateCommissionTemplatePolicy;
use AIArmada\FilamentAffiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {}

    public function boot(Application $app): void
    {
        Gate::policy(AffiliateCommissionTemplate::class, AffiliateCommissionTemplatePolicy::class);
    }
}
