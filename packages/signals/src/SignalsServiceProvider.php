<?php

declare(strict_types=1);

namespace AIArmada\Signals;

use AIArmada\Signals\Actions\IngestSignalEvent;
use AIArmada\Signals\Console\Commands\AggregateDailyMetricsCommand;
use AIArmada\Signals\Features\Alerts\EntryPoints\Console\ProcessSignalAlertsCommand;
use AIArmada\Signals\Contracts\BrowserContextResolverInterface;
use AIArmada\Signals\Contracts\SignalEventIngestor;
use AIArmada\Signals\Contracts\SignalLocationResolverContract;
use AIArmada\Signals\Models\SavedSignalReport;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertDelivery;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertLog;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;
use AIArmada\Signals\Models\SignalDailyMetric;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalGoal;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalInteractionRule;
use AIArmada\Signals\Models\SignalSegment;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use AIArmada\Signals\Platform\ExtensionRegistry;
use AIArmada\Signals\Platform\FeatureCatalog;
use AIArmada\Signals\Platform\FeatureModule;
use Illuminate\Contracts\Foundation\Application;
use AIArmada\Signals\Services\CommerceSignalsRecorder;
use AIArmada\Signals\Drivers\NominatimGeocoder;
use AIArmada\Signals\Services\SignalLocationResolverPipeline;
use AIArmada\Signals\Services\SignalMetricsAggregator;
use AIArmada\Signals\Services\TrackedPropertyResolver;
use AIArmada\Signals\Support\Browser\InjectSignalsTrackerIntoHtmlResponse;
use AIArmada\Signals\Support\Browser\SignalsBrowserContextManager;
use AIArmada\Signals\Support\Browser\SignalsBrowserContextResolver;
use AIArmada\Signals\Support\Browser\SignalsTrackerRenderer;
use AIArmada\Signals\Support\CommerceSignalsIntegrationRegistrar;
use AIArmada\Signals\Support\Http\Middleware\BootstrapSignalsBrowserContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class SignalsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('signals')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations()
            ->hasRoutes(['api'])
            ->hasCommand(AggregateDailyMetricsCommand::class)
            ->hasCommand(ProcessSignalAlertsCommand::class);
    }

    public function packageRegistered(): void
    {
        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn (Application $app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');
            if ($module instanceof FeatureModule) {
                $module->register($this->app);
            }
        }

        $this->app->bind(SignalEventIngestor::class, IngestSignalEvent::class);
        $this->app->singleton(SignalMetricsAggregator::class);
        $this->app->singleton(TrackedPropertyResolver::class);
        $this->app->singleton(CommerceSignalsRecorder::class);
        $this->app->scoped(SignalsBrowserContextManager::class);
        $this->app->bind(SignalsTrackerRenderer::class);
        $this->app->bind(InjectSignalsTrackerIntoHtmlResponse::class);
        $this->app->singleton(SignalLocationResolverPipeline::class, function ($app): SignalLocationResolverPipeline {
            $pipeline = new SignalLocationResolverPipeline;
            $pipeline->registerGeocoder($app->make(NominatimGeocoder::class));

            if ($app->bound(SignalLocationResolverContract::class)) {
                $pipeline->registerResolver($app->make(SignalLocationResolverContract::class));
            }

            return $pipeline;
        });

        $this->app->bind(BrowserContextResolverInterface::class, SignalsBrowserContextResolver::class);
    }

    public function packageBooted(): void
    {
        foreach (array_keys(FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__)['features']) as $name) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $name . '\\FeatureModule');
            if ($module instanceof FeatureModule) {
                $module->boot($this->app);
            }
        }

        $this->registerIngestionRateLimiter();
        $this->registerMorphMap();
        $this->registerSignalsTrackerDirective();
        $this->registerBrowserMiddleware();
        $this->registerBrowserAutoInjection();
        app(CommerceSignalsIntegrationRegistrar::class)->boot();
    }

    private function registerIngestionRateLimiter(): void
    {
        RateLimiter::for('signals-collect', static function (Request $request): array {
            $limit = max(1, (int) config('signals.ingestion.browser.rate_limit_per_minute', 120));

            return [
                Limit::perMinute($limit)->by('signals:collect:ip:' . ($request->ip() ?? 'unknown')),
                Limit::perMinute($limit)->by('signals:collect:write-key:' . hash('sha256', (string) $request->input('write_key', ''))),
            ];
        });
    }

    private function registerMorphMap(): void
    {
        Relation::morphMap([
            'tracked_property' => TrackedProperty::class,
            'signal_session' => SignalSession::class,
            'signal_event' => SignalEvent::class,
            'signal_identity' => SignalIdentity::class,
            'signal_goal' => SignalGoal::class,
            'signal_daily_metric' => SignalDailyMetric::class,
            'signal_interaction_rule' => SignalInteractionRule::class,
            'signal_alert_rule' => SignalAlertRule::class,
            'signal_alert_log' => SignalAlertLog::class,
            'signal_alert_delivery' => SignalAlertDelivery::class,
            'signal_segment' => SignalSegment::class,
            'saved_signal_report' => SavedSignalReport::class,
        ]);
    }

    private function registerSignalsTrackerDirective(): void
    {
        if (! $this->app->bound('blade.compiler')) {
            return;
        }

        Blade::directive('signalsTracker', static function (?string $expression): string {
            $attributes = $expression !== null && $expression !== '' ? $expression : '[]';

            return sprintf(
                "<?php \$__signalsTrackerAttributes = %s; echo app('%s')->render(is_array(\$__signalsTrackerAttributes) ? \$__signalsTrackerAttributes : []); ?>",
                $attributes,
                addslashes(SignalsTrackerRenderer::class),
            );
        });
    }

    private function registerBrowserMiddleware(): void
    {
        if (! $this->app->bound('router')) {
            return;
        }

        $this->app['router']->aliasMiddleware('signals.browser', BootstrapSignalsBrowserContext::class);

        if (! (bool) config('signals.integrations.browser.enabled', false)) {
            return;
        }

        if (! (bool) config('signals.integrations.browser.auto_register_middleware', true)) {
            return;
        }

        /** @var \Illuminate\Foundation\Http\Kernel $kernel */
        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup(
            (string) config('signals.integrations.browser.middleware_group', 'web'),
            BootstrapSignalsBrowserContext::class,
        );
    }

    private function registerBrowserAutoInjection(): void
    {
        Event::listen(RequestHandled::class, function (RequestHandled $event): void {
            app(InjectSignalsTrackerIntoHtmlResponse::class)->handle($event);
        });
    }
}
