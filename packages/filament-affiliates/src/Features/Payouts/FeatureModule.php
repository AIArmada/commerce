<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Payouts;

use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\FilamentAffiliates\Features\Payouts\EntryPoints\Filament\Policies\AffiliatePayoutPolicy;
use AIArmada\FilamentAffiliates\Features\Payouts\Queries\PayoutExportService;
use AIArmada\FilamentAffiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->singleton(PayoutExportService::class);
    }

    public function boot(Application $app): void
    {
        Gate::policy(AffiliatePayout::class, AffiliatePayoutPolicy::class);
    }
}
