<?php

declare(strict_types=1);

namespace AIArmada\Communications;

use AIArmada\Communications\Contracts\CommunicationAuditRecorder;
use AIArmada\Communications\Contracts\CommunicationCreator;
use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Contracts\CommunicationReferenceWriter;
use AIArmada\Communications\Contracts\CommunicationStatusRecalculator;
use AIArmada\Communications\Contracts\ConsentResolver;
use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Contracts\DeliveryFencing;
use AIArmada\Communications\Contracts\DestinationProtector;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Contracts\IdempotencyLock;
use AIArmada\Communications\Contracts\NativeNotificationCapture;
use AIArmada\Communications\Contracts\NotificationContentCapture;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Contracts\PreferenceResolver;
use AIArmada\Communications\Contracts\ProviderEventNormalizer;
use AIArmada\Communications\Contracts\ProviderWebhookRegistrar;
use AIArmada\Communications\Contracts\QuietHoursResolver;
use AIArmada\Communications\Contracts\RateLimiter as RateLimiterContract;
use AIArmada\Communications\Contracts\RecipientSnapshotResolver;
use AIArmada\Communications\Contracts\SuppressionResolver;
use AIArmada\Communications\Contracts\WebhookOwnerResolver;
use AIArmada\Communications\Drivers\Audit\NullCommunicationAuditRecorder;
use AIArmada\Communications\Drivers\Cache\IdempotencyLockService;
use AIArmada\Communications\Drivers\Eligibility\PermissiveEligibilityResolver;
use AIArmada\Communications\Drivers\Notifications\NullContentRenderer;
use AIArmada\Communications\Drivers\Notifications\NullRecipientSnapshotResolver;
use AIArmada\Communications\Drivers\Security\DestinationProtectorService;
use AIArmada\Communications\Drivers\Webhooks\ConfigWebhookOwnerResolver;
use AIArmada\Communications\Drivers\Webhooks\NullProviderEventNormalizer;
use AIArmada\Communications\Drivers\Webhooks\ProviderWebhookRegistrarService;
use AIArmada\Communications\EntryPoints\Commands\DispatchDueCommunicationsCommand;
use AIArmada\Communications\EntryPoints\Commands\ExpireCommunicationsCommand;
use AIArmada\Communications\EntryPoints\Commands\PruneCommunicationDataCommand;
use AIArmada\Communications\EntryPoints\Commands\ReconcileCommunicationStatusCommand;
use AIArmada\Communications\EntryPoints\Commands\ReplayWebhookEventsCommand;
use AIArmada\Communications\EntryPoints\Listeners\AutoCaptureNotificationListener;
use AIArmada\Communications\EntryPoints\Listeners\RecordNativeNotificationSending;
use AIArmada\Communications\EntryPoints\Listeners\RecordNativeNotificationSent;
use AIArmada\Communications\Features\Aggregate\Actions\AttachCommunicationReferenceAction;
use AIArmada\Communications\Features\Aggregate\Actions\CaptureRecipientContent;
use AIArmada\Communications\Features\Aggregate\Actions\CreateCommunicationAction;
use AIArmada\Communications\Features\Aggregate\Actions\RecalculateCommunicationStatusAction;
use AIArmada\Communications\Features\Delivery\Actions\CaptureNativeNotification;
use AIArmada\Communications\Features\Delivery\Actions\CommunicationManagerService;
use AIArmada\Communications\Features\Delivery\Actions\RecordDeliveryOutcome;
use AIArmada\Communications\Features\Delivery\Services\DeliveryFencingService;
use AIArmada\Communications\Features\Inbox\EntryPoints\Commands\PruneNotificationInboxesCommand;
use AIArmada\Communications\Features\Inbox\EntryPoints\Http\Livewire\InboxIndex;
use AIArmada\Communications\Features\RecipientRouting\Queries\CommunicationDestinationResolver;
use AIArmada\Communications\Features\Suppressions\Queries\ActiveSuppressionResolver;
use AIArmada\Communications\Integrations\Activitylog\ActivitylogCommunicationAuditRecorder;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Platform\ExtensionRegistry;
use AIArmada\Communications\Platform\FeatureCatalog;
use AIArmada\Communications\Platform\FeatureModule;
use AIArmada\Communications\Policies\CommunicationPolicy;
use AIArmada\Communications\Support\AutoCaptureState;
use AIArmada\Communications\Support\PayloadRedactorService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CommunicationsServiceProvider extends PackageServiceProvider
{
    /** @var list<FeatureModule> */
    private array $modules = [];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('communications')
            ->hasConfigFile()
            ->hasRoutes(['webhooks'])
            ->hasCommands([
                DispatchDueCommunicationsCommand::class,
                ExpireCommunicationsCommand::class,
                PruneCommunicationDataCommand::class,
                PruneNotificationInboxesCommand::class,
                ReconcileCommunicationStatusCommand::class,
                ReplayWebhookEventsCommand::class,
            ])
            ->hasViews()
            ->hasMigrations([
                '2000_01_01_000001_create_communication_batches_table',
                '2000_01_01_000002_create_communication_threads_table',
                '2000_01_01_000003_create_communications_table',
                '2000_01_01_000004_create_communication_recipients_table',
                '2000_01_01_000005_create_communication_contents_table',
                '2000_01_01_000006_create_communication_deliveries_table',
                '2000_01_01_000007_create_communication_attempts_table',
                '2000_01_01_000008_create_communication_events_table',
                '2000_01_01_000009_create_communication_templates_table',
                '2000_01_01_000010_create_communication_template_versions_table',
                '2000_01_01_000011_create_communication_preferences_table',
                '2000_01_01_000012_create_communication_suppressions_table',
                '2000_01_01_000013_create_communication_attachments_table',
                '2000_01_01_000014_create_communication_references_table',
                '2000_01_01_000015_create_communication_tracking_tokens_table',
                '2000_01_01_000016_create_communication_notification_inboxes_table',
                '2000_01_01_000017_create_communication_destinations_table',
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(CommunicationManager::class, CommunicationManagerService::class);
        $this->app->bind(CommunicationReferenceWriter::class, AttachCommunicationReferenceAction::class);
        $this->app->singleton(CommunicationRecorder::class, RecordDeliveryOutcome::class);
        $this->app->bind(CommunicationCreator::class, CreateCommunicationAction::class);
        $this->app->bind(NotificationContentCapture::class, CaptureRecipientContent::class);
        $this->app->bind(NativeNotificationCapture::class, CaptureNativeNotification::class);
        $this->app->bind(CommunicationStatusRecalculator::class, RecalculateCommunicationStatusAction::class);
        $this->app->singleton(DeliveryFencing::class, DeliveryFencingService::class);
        $this->app->singleton(DestinationProtector::class, DestinationProtectorService::class);
        $this->app->singleton(PayloadRedactor::class, PayloadRedactorService::class);
        $this->app->singleton(IdempotencyLock::class, IdempotencyLockService::class);
        $this->app->singleton(CommunicationAuditRecorder::class, NullCommunicationAuditRecorder::class);
        $this->app->scoped(AutoCaptureState::class);

        $this->app->bind(RecipientSnapshotResolver::class, NullRecipientSnapshotResolver::class);
        $this->app->bind(DestinationResolver::class, CommunicationDestinationResolver::class);
        $this->app->bind(ContentRenderer::class, NullContentRenderer::class);
        $this->app->bind(ConsentResolver::class, PermissiveEligibilityResolver::class);
        $this->app->bind(SuppressionResolver::class, ActiveSuppressionResolver::class);
        $this->app->bind(PreferenceResolver::class, PermissiveEligibilityResolver::class);
        $this->app->bind(QuietHoursResolver::class, PermissiveEligibilityResolver::class);
        $this->app->bind(RateLimiterContract::class, PermissiveEligibilityResolver::class);
        $this->app->bind(ProviderEventNormalizer::class, NullProviderEventNormalizer::class);
        $this->app->bind(ProviderWebhookRegistrar::class, ProviderWebhookRegistrarService::class);
        $this->app->bind(WebhookOwnerResolver::class, ConfigWebhookOwnerResolver::class);

        if (
            (bool) config('communications.integrations.activitylog.enabled', false)
            && class_exists(ActivitylogServiceProvider::class)
        ) {
            $this->app->singleton(
                CommunicationAuditRecorder::class,
                ActivitylogCommunicationAuditRecorder::class,
            );
        }
    }

    public function packageRegistered(): void
    {
        $catalog = FeatureCatalog::load(dirname(__DIR__), __NAMESPACE__);
        $this->app->singleton(ExtensionRegistry::class, fn ($app): ExtensionRegistry => new ExtensionRegistry($app, $catalog['extensions']));

        foreach (array_keys($catalog['features']) as $feature) {
            $module = $this->app->make(__NAMESPACE__ . '\\Features\\' . $feature . '\\FeatureModule');

            if (! $module instanceof FeatureModule) {
                throw new InvalidArgumentException('Invalid communications feature module: ' . $feature);
            }

            $module->register($this->app);
            $this->modules[] = $module;
        }
    }

    public function packageBooted(): void
    {
        foreach ($this->modules as $module) {
            $module->boot($this->app);
        }

        Gate::policy(Communication::class, CommunicationPolicy::class);
    }

    public function bootingPackage(): void
    {
        RateLimiterFacade::for('communications-webhooks', function (Request $request): Limit {
            $provider = (string) ($request->route('provider') ?? 'unknown');
            $key = ($request->ip() ?? 'unknown') . '|' . $provider;

            return Limit::perMinute(
                max(1, (int) config('communications.webhooks.rate_limit.max_attempts', 60)),
            )->by($key);
        });

        if (class_exists(Livewire::class)) {
            Livewire::component('communications.inbox-index', InboxIndex::class);
        }

        if (! (bool) config('communications.features.native_capture', true)) {
            return;
        }

        $dispatcher = $this->app->make(Dispatcher::class);
        $dispatcher->listen(NotificationSending::class, RecordNativeNotificationSending::class);
        $dispatcher->listen(NotificationSent::class, RecordNativeNotificationSent::class);

        $dispatcher->listen(NotificationSending::class, [AutoCaptureNotificationListener::class, 'handleSending']);
        $dispatcher->listen(NotificationSent::class, [AutoCaptureNotificationListener::class, 'handleSent']);
    }
}
