<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Fraud;

use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\FilamentAffiliates\Features\Fraud\EntryPoints\Filament\Policies\AffiliateFraudSignalPolicy;
use AIArmada\FilamentAffiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void {}

    public function boot(Application $app): void
    {
        Gate::policy(AffiliateFraudSignal::class, AffiliateFraudSignalPolicy::class);
    }
}
