<?php

declare(strict_types=1);

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
