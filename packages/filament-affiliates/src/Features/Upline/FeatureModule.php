<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Features\Upline;

use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Policies\AffiliateRankPolicy;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Policies\AffiliateRankHistoryPolicy;
use AIArmada\Affiliates\Models\AffiliateUpline;
use AIArmada\FilamentAffiliates\Features\Upline\EntryPoints\Filament\Policies\AffiliateUplinePolicy;
use AIArmada\FilamentAffiliates\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {}

    public function boot(Application $app): void
    {
        Gate::policy(AffiliateRank::class, AffiliateRankPolicy::class);
        Gate::policy(AffiliateRankHistory::class, AffiliateRankHistoryPolicy::class);
        Gate::policy(AffiliateUpline::class, AffiliateUplinePolicy::class);
    }
}
