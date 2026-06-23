<?php

declare(strict_types=1);

use AIArmada\Communications\Contracts\CommunicationAuditRecorder;
use AIArmada\Communications\Services\NullCommunicationAuditRecorder;

test('NullCommunicationAuditRecorder can be instantiated', function (): void {
    $recorder = new NullCommunicationAuditRecorder;

    expect($recorder)->toBeInstanceOf(NullCommunicationAuditRecorder::class);
});

test('recordWebhookReplay does not throw on NullCommunicationAuditRecorder', function (): void {
    $recorder = new NullCommunicationAuditRecorder;

    $recorder->recordWebhookReplay(
        communicationId: 'test-comm-id',
        actorType: null,
        actorId: null,
        reason: 'test replay',
        metadata: ['test' => true],
    );

    expect(true)->toBeTrue();
});

test('recordWebhookReplay is callable on the audit recorder contract', function (): void {
    $recorder = new NullCommunicationAuditRecorder;

    expect(method_exists($recorder, 'recordWebhookReplay'))->toBeTrue();
});

test('NullCommunicationAuditRecorder implements CommunicationAuditRecorder', function (): void {
    $recorder = new NullCommunicationAuditRecorder;

    expect($recorder)->toBeInstanceOf(
        CommunicationAuditRecorder::class,
    );
});

test('NullCommunicationAuditRecorder all record methods do not throw', function (): void {
    $recorder = new NullCommunicationAuditRecorder;

    $recorder->recordManualRetry('id', null, null);
    $recorder->recordCancel('id', null, null);
    $recorder->recordSuppressionCreate('id', null, null);
    $recorder->recordSuppressionLift('id', null, null);
    $recorder->recordPayloadRedact('id', null, null);

    expect(true)->toBeTrue();
});
