<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates;

use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Features\Tax\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateCommissionTemplate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
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
use AIArmada\FilamentAffiliates\Platform\ExtensionRegistry;
use AIArmada\FilamentAffiliates\Platform\FeatureCatalog;
use AIArmada\FilamentAffiliates\Platform\FeatureModule;
use Illuminate\Contracts\Foundation\Application;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentAffiliatesServiceProvider extends PackageServiceProvider
{
    /** @var array{features: array<string, string>, owners: array<string, string>, writes: array<string, list<string>>, extensions: array<string, list<string>>}|null */
    private ?array $catalog = null;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-affiliates')
            ->hasConfigFile('filament-affiliates')
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $catalog = $this->catalog();
        $this->app->singleton(ExtensionRegistry::class, fn (Application $app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');
            if ($module instanceof FeatureModule) {
                $module->register($this->app);
            }
        }

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
        foreach (array_keys($this->catalog()['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');
            if ($module instanceof FeatureModule) {
                $module->boot($this->app);
            }
        }

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
    /** @return array{features: array<string, string>, owners: array<string, string>, writes: array<string, list<string>>, extensions: array<string, list<string>>} */
    private function catalog(): array
    {
        return $this->catalog ??= FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
    }
}
