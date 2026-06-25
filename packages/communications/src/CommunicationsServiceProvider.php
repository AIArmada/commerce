<?php

declare(strict_types=1);

namespace AIArmada\Communications;

use AIArmada\Communications\Console\Commands\DispatchDueCommunicationsCommand;
use AIArmada\Communications\Console\Commands\ExpireCommunicationsCommand;
use AIArmada\Communications\Console\Commands\PruneCommunicationDataCommand;
use AIArmada\Communications\Console\Commands\PruneNotificationInboxesCommand;
use AIArmada\Communications\Console\Commands\ReconcileCommunicationStatusCommand;
use AIArmada\Communications\Console\Commands\ReplayWebhookEventsCommand;
use AIArmada\Communications\Contracts\CommunicationAuditRecorder;
use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Contracts\ConsentResolver;
use AIArmada\Communications\Contracts\ContentRenderer;
use AIArmada\Communications\Contracts\DestinationProtector;
use AIArmada\Communications\Contracts\DestinationResolver;
use AIArmada\Communications\Contracts\IdempotencyLock;
use AIArmada\Communications\Contracts\PayloadRedactor;
use AIArmada\Communications\Contracts\PreferenceResolver;
use AIArmada\Communications\Contracts\QuietHoursResolver;
use AIArmada\Communications\Contracts\RateLimiter;
use AIArmada\Communications\Contracts\RecipientSnapshotResolver;
use AIArmada\Communications\Contracts\SuppressionResolver;
use AIArmada\Communications\Contracts\WebhookOwnerResolver;
use AIArmada\Communications\Http\Livewire\InboxIndex;
use AIArmada\Communications\Integrations\Activitylog\ActivitylogCommunicationAuditRecorder;
use AIArmada\Communications\Listeners\AutoCaptureNotificationListener;
use AIArmada\Communications\Listeners\RecordNativeNotificationSending;
use AIArmada\Communications\Listeners\RecordNativeNotificationSent;
use AIArmada\Communications\Services\CommunicationManagerService;
use AIArmada\Communications\Services\CommunicationRecorderService;
use AIArmada\Communications\Services\NotifiableDestinationResolver;
use AIArmada\Communications\Services\NullCommunicationAuditRecorder;
use AIArmada\Communications\Services\NullConsentResolver;
use AIArmada\Communications\Services\NullContentRenderer;
use AIArmada\Communications\Services\NullPreferenceResolver;
use AIArmada\Communications\Services\NullQuietHoursResolver;
use AIArmada\Communications\Services\NullRateLimiter;
use AIArmada\Communications\Services\NullRecipientSnapshotResolver;
use AIArmada\Communications\Services\NullSuppressionResolver;
use AIArmada\Communications\Support\AutoCaptureState;
use AIArmada\Communications\Support\DestinationProtectorService;
use AIArmada\Communications\Support\IdempotencyLockService;
use AIArmada\Communications\Support\PayloadRedactorService;
use AIArmada\Communications\Webhooks\Contracts\ProviderEventNormalizer;
use AIArmada\Communications\Webhooks\Contracts\ProviderWebhookRegistrar;
use AIArmada\Communications\Webhooks\Normalizers\NullProviderEventNormalizer;
use AIArmada\Communications\Webhooks\Registrars\ProviderWebhookRegistrarService;
use AIArmada\Communications\Webhooks\WebhookOwnerResolver as DefaultWebhookOwnerResolver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Livewire\Livewire;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CommunicationsServiceProvider extends PackageServiceProvider
{
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
                '2000_01_01_000016_create_notification_inboxes_table',
                '2000_01_01_000017_reconcile_configured_communication_table_names',
                '2000_01_01_000018_add_suppressed_at_to_communication_deliveries',
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(CommunicationManager::class, CommunicationManagerService::class);
        $this->app->singleton(CommunicationRecorder::class, CommunicationRecorderService::class);
        $this->app->singleton(DestinationProtector::class, DestinationProtectorService::class);
        $this->app->singleton(PayloadRedactor::class, PayloadRedactorService::class);
        $this->app->singleton(IdempotencyLock::class, IdempotencyLockService::class);
        $this->app->singleton(CommunicationAuditRecorder::class, NullCommunicationAuditRecorder::class);
        $this->app->scoped(AutoCaptureState::class);

        $this->app->bind(RecipientSnapshotResolver::class, NullRecipientSnapshotResolver::class);
        $this->app->bind(DestinationResolver::class, NotifiableDestinationResolver::class);
        $this->app->bind(ContentRenderer::class, NullContentRenderer::class);
        $this->app->bind(ConsentResolver::class, NullConsentResolver::class);
        $this->app->bind(SuppressionResolver::class, NullSuppressionResolver::class);
        $this->app->bind(PreferenceResolver::class, NullPreferenceResolver::class);
        $this->app->bind(QuietHoursResolver::class, NullQuietHoursResolver::class);
        $this->app->bind(RateLimiter::class, NullRateLimiter::class);
        $this->app->bind(ProviderEventNormalizer::class, NullProviderEventNormalizer::class);
        $this->app->bind(ProviderWebhookRegistrar::class, ProviderWebhookRegistrarService::class);
        $this->app->bind(WebhookOwnerResolver::class, DefaultWebhookOwnerResolver::class);

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

    public function bootingPackage(): void
    {
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
