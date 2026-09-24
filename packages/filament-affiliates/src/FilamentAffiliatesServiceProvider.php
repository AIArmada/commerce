<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionTemplate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Models\AffiliateUpline;
use AIArmada\Affiliates\Support\Integrations\CartBridge;
use AIArmada\Affiliates\Support\Integrations\VoucherBridge;
use AIArmada\FilamentAffiliates\Policies\AffiliateCommissionTemplatePolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateConversionPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateFraudSignalPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateLinkPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliatePayoutPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliatePolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateProgramPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateRankHistoryPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateRankPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateSupportTicketPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateTaxDocumentPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateTouchpointPolicy;
use AIArmada\FilamentAffiliates\Policies\AffiliateUplinePolicy;
use AIArmada\FilamentAffiliates\Services\AffiliateStatsAggregator;
use AIArmada\FilamentAffiliates\Services\PayoutExportService;
use Filament\Facades\Filament;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentAffiliatesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-affiliates')
            ->hasConfigFile('filament-affiliates')
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentAffiliatesPlugin::class);
        $this->app->singleton(AffiliateStatsAggregator::class);
        $this->app->singleton(VoucherBridge::class);
        $this->app->singleton(PayoutExportService::class);

        $this->registerSettingsMigrationPath();
    }

    private function registerSettingsMigrationPath(): void
    {
        $packagePath = __DIR__ . '/../database/settings';

        if (! is_dir($packagePath)) {
            return;
        }

        $paths = config('settings.migrations_paths', []);

        if (! in_array($packagePath, $paths, true)) {
            $paths[] = $packagePath;

            config(['settings.migrations_paths' => $paths]);
        }
    }

    public function packageBooted(): void
    {
        // Without registration `filament:assets` never publishes the portal
        // stylesheet, so portal pages render unstyled (giant empty-state icon).
        FilamentAsset::register([
            Css::make('affiliate-portal', __DIR__ . '/../resources/css/affiliate-portal.css'),
        ], 'aiarmada/filament-affiliates');

        Filament::serving(function (): void {
            if (config('filament-affiliates.integrations.filament_cart', true)) {
                app(CartBridge::class)->warm();
            }

            if (config('filament-affiliates.integrations.filament_vouchers', true)) {
                app(VoucherBridge::class)->warm();
            }
        });

        Gate::policy(Affiliate::class, AffiliatePolicy::class);
        Gate::policy(AffiliateConversion::class, AffiliateConversionPolicy::class);
        Gate::policy(AffiliateFraudSignal::class, AffiliateFraudSignalPolicy::class);
        Gate::policy(AffiliatePayout::class, AffiliatePayoutPolicy::class);
        Gate::policy(AffiliateProgram::class, AffiliateProgramPolicy::class);
        Gate::policy(AffiliateLink::class, AffiliateLinkPolicy::class);
        Gate::policy(AffiliateUpline::class, AffiliateUplinePolicy::class);
        Gate::policy(AffiliateCommissionTemplate::class, AffiliateCommissionTemplatePolicy::class);
        Gate::policy(AffiliateRank::class, AffiliateRankPolicy::class);
        Gate::policy(AffiliateRankHistory::class, AffiliateRankHistoryPolicy::class);
        Gate::policy(AffiliateSupportTicket::class, AffiliateSupportTicketPolicy::class);
        Gate::policy(AffiliateTaxDocument::class, AffiliateTaxDocumentPolicy::class);
        Gate::policy(AffiliateTouchpoint::class, AffiliateTouchpointPolicy::class);
    }
}
