<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Programs;

use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\FilamentAffiliates\Features\Programs\EntryPoints\Filament\Policies\AffiliateProgramPolicy;
use AIArmada\FilamentAffiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {}

    public function boot(Application $app): void
    {
        Gate::policy(AffiliateProgram::class, AffiliateProgramPolicy::class);
    }
}
