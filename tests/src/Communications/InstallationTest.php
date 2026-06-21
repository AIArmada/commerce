<?php

declare(strict_types=1);

use AIArmada\Communications\CommunicationsServiceProvider;
use AIArmada\Communications\Contracts\CommunicationManager;
use AIArmada\Communications\Contracts\CommunicationRecorder;
use AIArmada\Communications\Facades\Communications;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationAttachment;
use AIArmada\Communications\Models\CommunicationAttempt;
use AIArmada\Communications\Models\CommunicationBatch;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationEvent;
use AIArmada\Communications\Models\CommunicationPreference;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Communications\Models\CommunicationReference;
use AIArmada\Communications\Models\CommunicationSuppression;
use AIArmada\Communications\Models\CommunicationTemplate;
use AIArmada\Communications\Models\CommunicationTemplateVersion;
use AIArmada\Communications\Models\CommunicationThread;
use AIArmada\Communications\Models\CommunicationTrackingToken;
use AIArmada\Communications\Services\CommunicationManagerService;
use AIArmada\Communications\Services\CommunicationRecorderService;
use Illuminate\Support\Facades\Schema;

test('service provider registers', function (): void {
    $providers = app()->getLoadedProviders();

    expect(isset($providers[CommunicationsServiceProvider::class]))->toBeTrue();
});

test('config publishes and reads correctly', function (): void {
    expect(config('communications.database.table_prefix'))->toBeString()->toBe('');
    expect(config('communications.database.tables.communications'))->toBe('communications');
    expect(config('communications.database.tables.deliveries'))->toBe('communication_deliveries');
    expect(config('communications.features.owner.enabled'))->toBeTrue();
    expect(config('communications.defaults.priority'))->toBe('normal');
    expect(config('communications.defaults.max_attempts'))->toBe(3);
});

test('all 15 communication tables exist after migration', function (): void {
    $tables = [
        'communication_batches',
        'communication_threads',
        'communications',
        'communication_recipients',
        'communication_contents',
        'communication_deliveries',
        'communication_attempts',
        'communication_events',
        'communication_templates',
        'communication_template_versions',
        'communication_preferences',
        'communication_suppressions',
        'communication_attachments',
        'communication_references',
        'communication_tracking_tokens',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))
            ->toBeTrue("Expected table '$table' to exist");
    }
});

test('all model classes instantiate with correct table names', function (): void {
    expect((new CommunicationBatch)->getTable())->toBe('communication_batches');
    expect((new CommunicationThread)->getTable())->toBe('communication_threads');
    expect((new Communication)->getTable())->toBe('communications');
    expect((new CommunicationRecipient)->getTable())->toBe('communication_recipients');
    expect((new CommunicationContent)->getTable())->toBe('communication_contents');
    expect((new CommunicationDelivery)->getTable())->toBe('communication_deliveries');
    expect((new CommunicationAttempt)->getTable())->toBe('communication_attempts');
    expect((new CommunicationEvent)->getTable())->toBe('communication_events');
    expect((new CommunicationTemplate)->getTable())->toBe('communication_templates');
    expect((new CommunicationTemplateVersion)->getTable())->toBe('communication_template_versions');
    expect((new CommunicationPreference)->getTable())->toBe('communication_preferences');
    expect((new CommunicationSuppression)->getTable())->toBe('communication_suppressions');
    expect((new CommunicationAttachment)->getTable())->toBe('communication_attachments');
    expect((new CommunicationReference)->getTable())->toBe('communication_references');
    expect((new CommunicationTrackingToken)->getTable())->toBe('communication_tracking_tokens');
});

test('service container binds contracts to implementations', function (): void {
    expect(app(CommunicationManager::class))->toBeInstanceOf(CommunicationManagerService::class);
    expect(app(CommunicationRecorder::class))->toBeInstanceOf(CommunicationRecorderService::class);
});

test('facade resolves the manager', function (): void {
    expect(Communications::getFacadeRoot())->toBeInstanceOf(CommunicationManager::class);
});

test('all models have UUID primary keys', function (): void {
    $models = [
        new CommunicationBatch,
        new CommunicationThread,
        new Communication,
        new CommunicationRecipient,
        new CommunicationContent,
        new CommunicationDelivery,
        new CommunicationAttempt,
        new CommunicationEvent,
        new CommunicationTemplate,
        new CommunicationTemplateVersion,
        new CommunicationPreference,
        new CommunicationSuppression,
        new CommunicationAttachment,
        new CommunicationReference,
        new CommunicationTrackingToken,
    ];

    foreach ($models as $model) {
        expect($model->getKeyType())->toBe('string');
        expect($model->getIncrementing())->toBeFalse();
    }
});
