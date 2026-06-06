<?php

declare(strict_types=1);

namespace AIArmada\Affiliates;

use AIArmada\Affiliates\Actions\Affiliates\ResolvePublicAffiliateReferralContext;
use AIArmada\Affiliates\Cart\AffiliateDiscountConditionProvider;
use AIArmada\Affiliates\Listeners\RecordCommissionForOrder;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Models\AffiliateCommissionTemplate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateDailyStat;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateNetwork;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Models\AffiliatePayoutHold;
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Models\AffiliateProgramCreative;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Models\AffiliateProgramTier;
use AIArmada\Affiliates\Models\AffiliateRank;
use AIArmada\Affiliates\Models\AffiliateRankHistory;
use AIArmada\Affiliates\Models\AffiliateSupportMessage;
use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Models\AffiliateTrainingModule;
use AIArmada\Affiliates\Models\AffiliateTrainingProgress;
use AIArmada\Affiliates\Models\AffiliateVolumeTier;
use AIArmada\Affiliates\Services\AffiliatePayoutService;
use AIArmada\Affiliates\Services\AffiliateRegistrationService;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\Services\AttributionModel;
use AIArmada\Affiliates\Services\CommissionCalculator;
use AIArmada\Affiliates\Services\CommissionMaturityService;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\Services\DailyAggregationService;
use AIArmada\Affiliates\Services\FraudDetectionService;
use AIArmada\Affiliates\Services\NetworkService;
use AIArmada\Affiliates\Services\PayoutReconciliationService;
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\Affiliates\Services\RankQualificationService;
use AIArmada\Affiliates\Support\Integrations\CartIntegrationRegistrar;
use AIArmada\Affiliates\Support\Integrations\VoucherIntegrationRegistrar;
use AIArmada\Affiliates\Support\Middleware\HydratePublicAffiliateReferralContext;
use AIArmada\Affiliates\Support\Middleware\TrackAffiliateCookie;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use AIArmada\Cart\CartManager;
use AIArmada\Cart\Conditions\ConditionProviderRegistry;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AffiliatesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('affiliates')
            ->hasConfigFile('affiliates')
            ->hasViews('affiliates')
            ->runsMigrations()
            ->discoversMigrations()
            ->hasRoutes(['api', 'web'])
            ->hasCommands([
                Console\Commands\ExportAffiliatePayoutCommand::class,
                Console\Commands\AggregateDailyStatsCommand::class,
                Console\Commands\ProcessRankUpgradesCommand::class,
                Console\Commands\ProcessScheduledPayoutsCommand::class,
                Console\Commands\ProcessCommissionMaturityCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(CommissionCalculator::class);
        $this->app->singleton(AffiliateService::class);
        $this->app->singleton(AffiliatePayoutService::class);
        $this->app->singleton(AffiliateRegistrationService::class);
        $this->app->singleton(WebhookDispatcher::class);
        $this->app->singleton(AttributionModel::class);
        $this->app->singleton(NetworkService::class);
        $this->app->singleton(RankQualificationService::class);
        $this->app->singleton(DailyAggregationService::class);
        $this->app->singleton(FraudDetectionService::class);
        $this->app->singleton(PayoutProcessorFactory::class);
        $this->app->singleton(CommissionRuleEngine::class);
        $this->app->singleton(ProgramService::class);
        $this->app->singleton(CommissionMaturityService::class);
        $this->app->singleton(PayoutReconciliationService::class);

        $this->app->singleton(CartIntegrationRegistrar::class);
        $this->app->singleton(VoucherIntegrationRegistrar::class);
        $this->app->singleton(AffiliateDiscountConditionProvider::class);

        $this->app->alias(AffiliateService::class, 'affiliates');
    }

    public function packageBooted(): void
    {
        $this->registerMorphMap();

        Blade::anonymousComponentNamespace('affiliates::components', 'affiliates');

        if (
            config('affiliates.features.cart_integration.enabled', true)
            && config('affiliates.cart.register_manager_proxy', true)
        ) {
            app(CartIntegrationRegistrar::class)->register();
        }

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
        ) {
            Event::listen(CommissionAttributionRequired::class, RecordCommissionForOrder::class);
        }

        if (config('affiliates.cookies.enabled', true)) {
            $this->registerCookieTrackingMiddleware();
        }

        $this->registerPublicPageSupport();
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
            'affiliate_network' => AffiliateNetwork::class,
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
            'affiliate_training_module' => AffiliateTrainingModule::class,
            'affiliate_training_progress' => AffiliateTrainingProgress::class,
            'affiliate_volume_tier' => AffiliateVolumeTier::class,
        ]);
    }

    /**
     * Register the affiliate cookie tracking middleware.
     */
    private function registerCookieTrackingMiddleware(): void
    {
        if (! $this->app->bound('router')) {
            return;
        }

        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('affiliates.cookie', TrackAffiliateCookie::class);

        if (config('affiliates.cookies.auto_register_middleware', true)) {
            $router->pushMiddlewareToGroup('web', TrackAffiliateCookie::class);
        }
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

            if (config('affiliates.public_pages.auto_register_middleware', true)) {
                $router->pushMiddlewareToGroup('web', HydratePublicAffiliateReferralContext::class);
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
}
