<?php

declare(strict_types=1);

use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Enums\TemplateStatus;
use AIArmada\Communications\Enums\ThreadStatus;
use AIArmada\Communications\Models\Communication;
use AIArmada\Communications\Models\CommunicationAttachment;
use AIArmada\Communications\Models\CommunicationAttempt;
use AIArmada\Communications\Models\CommunicationBatch;
use AIArmada\Communications\Models\CommunicationContent;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Communications\Models\CommunicationDestination;
use AIArmada\Communications\Models\CommunicationEvent;
use AIArmada\Communications\Models\CommunicationPreference;
use AIArmada\Communications\Models\CommunicationRecipient;
use AIArmada\Communications\Models\CommunicationReference;
use AIArmada\Communications\Models\CommunicationSuppression;
use AIArmada\Communications\Models\CommunicationTemplate;
use AIArmada\Communications\Models\CommunicationTemplateVersion;
use AIArmada\Communications\Models\CommunicationThread;
use AIArmada\Communications\Models\CommunicationTrackingToken;
use AIArmada\Communications\Models\NotificationInbox;

test('communications models do not mass assign owner columns', function (string $modelClass): void {
    $model = new $modelClass([
        'owner_type' => 'attacker',
        'owner_id' => 'attacker-id',
    ]);

    expect($model->owner_type)->toBeNull()
        ->and($model->owner_id)->toBeNull();
})->with([
    Communication::class,
    CommunicationAttachment::class,
    CommunicationAttempt::class,
    CommunicationBatch::class,
    CommunicationContent::class,
    CommunicationDelivery::class,
    CommunicationEvent::class,
    CommunicationPreference::class,
    CommunicationRecipient::class,
    CommunicationReference::class,
    CommunicationSuppression::class,
    CommunicationTemplate::class,
    CommunicationTemplateVersion::class,
    CommunicationThread::class,
    CommunicationTrackingToken::class,
    NotificationInbox::class,
]);

test('communications models do not mass assign morph identity columns', function (string $modelClass, array $columns): void {
    $attributes = [];

    foreach ($columns as $column) {
        $attributes[$column] = 'attacker-type';
    }

    $model = new $modelClass($attributes);

    foreach ($columns as $column) {
        expect($model->getAttribute($column))->toBeNull();
    }
})->with([
    'communication' => [Communication::class, ['subject_type', 'sender_type']],
    'attachment' => [CommunicationAttachment::class, ['attachable_type']],
    'destination' => [CommunicationDestination::class, ['recipient_type']],
    'preference' => [CommunicationPreference::class, ['recipient_type', 'scope_type']],
    'recipient' => [CommunicationRecipient::class, ['recipient_type']],
    'reference' => [CommunicationReference::class, ['reference_type']],
    'suppression' => [CommunicationSuppression::class, ['recipient_type', 'created_by_type']],
    'thread' => [CommunicationThread::class, ['subject_type']],
    'inbox' => [NotificationInbox::class, ['recipient_type']],
]);

test('communications models do not mass assign workflow status', function (string $modelClass, mixed $status): void {
    $model = new $modelClass(['status' => $status]);

    expect($model->getAttribute('status'))->toBeNull();
})->with([
    'communication' => [Communication::class, CommunicationStatus::Failed],
    'batch' => [CommunicationBatch::class, 'cancelled'],
    'delivery' => [CommunicationDelivery::class, DeliveryStatus::Failed],
    'destination' => [CommunicationDestination::class, 'suspended'],
    'template' => [CommunicationTemplate::class, TemplateStatus::Published],
    'thread' => [CommunicationThread::class, ThreadStatus::Closed],
]);
