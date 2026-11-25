<?php

declare(strict_types=1);

namespace AIArmada\Affiliates;

use AIArmada\Affiliates\Features\Attribution\EntryPoints\Http\ResolvePublicAffiliateReferralContext;
use AIArmada\Affiliates\Cart\AffiliateDiscountConditionProvider;
use AIArmada\Affiliates\Contracts\AffiliateWebhookTransport;
use AIArmada\Affiliates\Contracts\CreativeAssetRemoval;
use AIArmada\Affiliates\Contracts\NetworkPostbackGateway;
use AIArmada\Affiliates\Contracts\PayoutCsvWriter;
use AIArmada\Affiliates\Contracts\PayoutProcessorRegistry;
use AIArmada\Affiliates\Contracts\TaxRecipientProvider;
use AIArmada\Affiliates\Contracts\TaxWorksheetWriter;
use AIArmada\Affiliates\Drivers\Exports\LocalPayoutCsvWriter;
use AIArmada\Affiliates\Drivers\Media\SpatieCreativeAssetRemoval;
use AIArmada\Affiliates\Drivers\Network\HttpNetworkPostbackGateway;
use AIArmada\Affiliates\Drivers\Payouts\PayoutProcessorFactory;
use AIArmada\Affiliates\Drivers\Tax\SpatieTaxWorksheetWriter;
use AIArmada\Affiliates\Drivers\Tax\UnavailableTaxRecipientProvider;
use AIArmada\Affiliates\Drivers\Webhooks\PinnedAffiliateWebhookTransport;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Events\AffiliateProgramJoined;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Features\Identity\EntryPoints\Listeners\AutoApproveOpenAffiliate;
use AIArmada\Affiliates\Features\Reporting\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Features\Tax\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateUpline;
use AIArmada\Affiliates\Features\Conversions\EntryPoints\Listeners\NotifyConversionRecorded;
use AIArmada\Affiliates\Features\Programs\EntryPoints\Listeners\NotifyProgramJoined;
use AIArmada\Affiliates\Features\Conversions\Api\OrderCommissionRegistration;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateCommissionTemplate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateRankHistory;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Features\Commissions\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Platform\ExtensionRegistry;
use AIArmada\Affiliates\Platform\FeatureCatalog;
use AIArmada\Affiliates\Platform\FeatureModule;
use AIArmada\Affiliates\Support\Integrations\CartBridge;
use AIArmada\Affiliates\Features\Attribution\EntryPoints\Listeners\VoucherIntegrationRegistrar;
use AIArmada\Affiliates\Features\Attribution\EntryPoints\Middleware\CaptureAffiliateReferralFromPath;
use AIArmada\Affiliates\Features\Attribution\EntryPoints\Middleware\HydratePublicAffiliateReferralContext;
use AIArmada\Affiliates\Features\Attribution\EntryPoints\Middleware\TrackAffiliateCookie;
use AIArmada\Cart\CartManager;
use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Links\Contracts\LinkGateInterface;
use AIArmada\Links\Events\LinkClicked;
use AIArmada\Links\LinksServiceProvider;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AffiliatesServiceProvider extends PackageServiceProvider
{
    /** @var array{features: array<string, string>, owners: array<string, string>, writes: array<string, list<string>>, extensions: array<string, list<string>>}|null */
    private ?array $catalog = null;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('affiliates')
            ->hasConfigFile('affiliates')
            ->hasViews('affiliates')
            ->runsMigrations()
            ->discoversMigrations()
            ->hasCommands([
                Features\Commissions\EntryPoints\Console\AwardPerformanceBonusesCommand::class,
                Features\Payouts\EntryPoints\Console\ExportAffiliatePayoutCommand::class,
                Features\Reporting\EntryPoints\Commands\AggregateDailyStatsCommand::class,
                Features\Upline\EntryPoints\Console\ProcessRankUpgradesCommand::class,
                Features\Payouts\EntryPoints\Console\ProcessScheduledPayoutsCommand::class,
                Features\Conversions\EntryPoints\Console\ProcessCommissionMaturityCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $catalog = $this->catalog();

        $this->app->bindIf(CreativeAssetRemoval::class, SpatieCreativeAssetRemoval::class);
        $this->app->bindIf(PayoutCsvWriter::class, LocalPayoutCsvWriter::class);
        $this->app->bindIf(NetworkPostbackGateway::class, HttpNetworkPostbackGateway::class);
        $this->app->bindIf(TaxRecipientProvider::class, UnavailableTaxRecipientProvider::class);
        $this->app->bindIf(TaxWorksheetWriter::class, SpatieTaxWorksheetWriter::class);
        $this->app->bindIf(AffiliateWebhookTransport::class, PinnedAffiliateWebhookTransport::class);

        $this->app->singleton(ExtensionRegistry::class, fn (Application $app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $name) {
            $module = $this->app->make($this->moduleClass($name));

            if ($module instanceof FeatureModule) {
                $module->register($this->app);
            }
        }

        $this->app->singleton(PayoutProcessorRegistry::class, PayoutProcessorFactory::class);
        $this->app->singleton(Support\Catalog\PromotableRegistry::class);
        $this->app->singleton(CartBridge::class);


        $this->app->singleton(VoucherIntegrationRegistrar::class);
        $this->app->singleton(AffiliateDiscountConditionProvider::class);

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
            $module = $this->app->make($this->moduleClass($name));

            if ($module instanceof FeatureModule) {
                $module->boot($this->app);
            }
        }

        $this->registerMorphMap();
        $this->loadRoutesFrom(__DIR__ . '/Features/Identity/Routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/Features/Programs/Routes/api.php');

        Blade::anonymousComponentNamespace('affiliates::components', 'affiliates');

        if (config('affiliates.features.voucher_integration.enabled', true)) {
            app(VoucherIntegrationRegistrar::class)->register();
        }

        if (
            config('affiliates.features.cart_integration.enabled', true)
            && class_exists(ConditionProviderRegistry::class)
        ) {
            $this->app->make(ConditionProviderRegistry::class)
                ->register(AffiliateDiscountConditionProvider::class);
        }

        if (
            config('affiliates.features.commission_tracking.enabled', true)
            && class_exists(CommissionAttributionRequired::class)
            && class_exists(CartManager::class)
            && ! $this->networkCoordinatesAttribution()
        ) {
            $this->app->make(OrderCommissionRegistration::class)->register();
        }

        Event::listen(AffiliateProgramJoined::class, NotifyProgramJoined::class);
        Event::listen(AffiliateConversionRecorded::class, NotifyConversionRecorded::class);
        Event::listen(AffiliateConversionRecorded::class, AutoApproveOpenAffiliate::class);

        $this->registerPublicPageSupport();

        if (config('affiliates.cookies.enabled', true)) {
            $this->registerCookieTrackingMiddleware();
        }

        if (class_exists(LinksServiceProvider::class)) {
            $this->app->tag([Support\Links\AffiliateLinkGate::class], LinkGateInterface::class);
            Event::listen(LinkClicked::class, Features\Attribution\EntryPoints\Listeners\IncrementAffiliateLinkClicks::class);
        }
    }

    private function registerMorphMap(): void
    {
        Relation::morphMap([
            'affiliate' => Affiliate::class,
            'affiliate_attribution' => AffiliateAttribution::class,
            'affiliate_balance' => AffiliateBalance::class,
            'affiliate_commission_promotion' => AffiliateCommissionPromotion::class,
            'affiliate_commission_rule' => AffiliateCommissionRule::class,
            'affiliate_commission_template' => AffiliateCommissionTemplate::class,
            'affiliate_conversion' => AffiliateConversion::class,
            'affiliate_daily_stat' => AffiliateDailyStat::class,
            'affiliate_fraud_signal' => AffiliateFraudSignal::class,
            'affiliate_link' => AffiliateLink::class,
            'affiliate_upline' => AffiliateUpline::class,
            'affiliate_payout' => AffiliatePayout::class,
            'affiliate_payout_event' => AffiliatePayoutEvent::class,
            'affiliate_payout_hold' => AffiliatePayoutHold::class,
            'affiliate_payout_method' => AffiliatePayoutMethod::class,
            'affiliate_program' => AffiliateProgram::class,
            'affiliate_program_creative' => AffiliateProgramCreative::class,
            'affiliate_program_membership' => AffiliateProgramMembership::class,
            'affiliate_program_tier' => AffiliateProgramTier::class,
            'affiliate_rank' => AffiliateRank::class,
            'affiliate_rank_history' => AffiliateRankHistory::class,
            'affiliate_support_message' => AffiliateSupportMessage::class,
            'affiliate_support_ticket' => AffiliateSupportTicket::class,
            'affiliate_tax_document' => AffiliateTaxDocument::class,
            'affiliate_touchpoint' => AffiliateTouchpoint::class,
            'affiliate_volume_tier' => AffiliateVolumeTier::class,
        ]);
    }

    /**
     * Register the affiliate cookie tracking middleware.
     */
    private function registerCookieTrackingMiddleware(): void
    {
        $this->excludeTrackingCookieFromEncryption();

        if (! $this->app->bound('router')) {
            return;
        }

        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('affiliates.cookie', TrackAffiliateCookie::class);

        if (config('affiliates.cookies.auto_register_middleware', true) && $this->app->bound(Kernel::class)) {
            $this->app->make(Kernel::class)->pushMiddleware(TrackAffiliateCookie::class);
        }
    }

    /**
     * Keep tracking-cookie reads consistent between middleware and app code.
     *
     * The tracking middleware is global, so it writes the cookie outside
     * Laravel's encryption pass. Without this exclusion EncryptCookies
     * would null the plaintext value before controllers run, silently
     * breaking cookie-to-cart hydration (CartBridge). The value is an
     * opaque random identifier, so plaintext transport matches the
     * middleware's existing write behavior.
     */
    private function excludeTrackingCookieFromEncryption(): void
    {
        if (! class_exists(EncryptCookies::class)) {
            return;
        }

        EncryptCookies::except(
            [(string) config('affiliates.cookies.name', 'affiliate_session')]
        );
    }

    private function registerPublicPageSupport(): void
    {
        if (! config('affiliates.public_pages.enabled', true)) {
            return;
        }

        if ($this->app->bound('router')) {
            /** @var Router $router */
            $router = $this->app['router'];
            $router->aliasMiddleware('affiliates.public_context', HydratePublicAffiliateReferralContext::class);
            $router->aliasMiddleware('affiliates.referral_path', CaptureAffiliateReferralFromPath::class);

            if (config('affiliates.public_pages.auto_register_middleware', true) && $this->app->bound(Kernel::class)) {
                /** @var \Illuminate\Foundation\Http\Kernel $kernel */
                $kernel = $this->app->make(Kernel::class);
                $kernel->appendMiddlewareToGroup('web', HydratePublicAffiliateReferralContext::class);
                $kernel->pushMiddleware(CaptureAffiliateReferralFromPath::class);
            }
        }

        View::composer('*', static function ($view): void {
            if (! app()->bound('request')) {
                return;
            }

            $request = request();
            $requestAttributeKey = HydratePublicAffiliateReferralContext::requestAttributeKey();
            $publicReferralContext = $request->attributes->get($requestAttributeKey);

            if (! $request->attributes->has($requestAttributeKey)) {
                $publicReferralContext = app(ResolvePublicAffiliateReferralContext::class)->handle($request);
                $request->attributes->set($requestAttributeKey, $publicReferralContext);
            }

            $view->with(
                (string) config('affiliates.public_pages.view_data_key', 'affiliateReferral'),
                $publicReferralContext
            );
        });
    }

    /**
     * Whether the marketplace coordinates listener order.
     *
     * When both packages are installed, the network provider registers
     * the attribution listeners in decision order (network provisional,
     * engine, network finalizer), so the engine must not self-register
     * and double-record. String class check only — the engine imports
     * no network code; standalone behavior is untouched.
     */
    private function networkCoordinatesAttribution(): bool
    {
        $providers = $this->app->getLoadedProviders();

        return isset($providers['AIArmada\AffiliateNetwork\AffiliateNetworkServiceProvider'])
            && config('affiliate-network.checkout.enabled', false)
            && config('affiliate-network.checkout.listen_for_orders', true);
    }

    private function moduleClass(string $feature): string
    {
        return __NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule';
    }

    /** @return array{features: array<string, string>, owners: array<string, string>, writes: array<string, list<string>>, extensions: array<string, list<string>>} */
    private function catalog(): array
    {
        return $this->catalog ??= FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
    }
}
