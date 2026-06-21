<?php

declare(strict_types=1);

namespace AIArmada\Communications\Integrations\Activitylog;

use AIArmada\Communications\Contracts\CommunicationAuditRecorder;
use Carbon\CarbonImmutable;
use Spatie\Activitylog\ActivitylogServiceProvider;

final class ActivitylogCommunicationAuditRecorder implements CommunicationAuditRecorder
{
    private string $logName;

    private const ACTION_MAP = [
        'manual_retry' => 'communication.manual_retry',
        'cancel' => 'communication.cancel',
        'suppression_create' => 'communication.suppression_create',
        'suppression_lift' => 'communication.suppression_lift',
        'payload_redact' => 'communication.payload_redact',
        'webhook_replay' => 'communication.webhook_replay',
    ];

    public function __construct(?string $logName = null)
    {
        $this->logName = $logName ?? 'communications';
    }

    public function recordManualRetry(
        string $communicationId,
        ?string $actorType,
        ?string $actorId,
        ?string $reason = null,
        array $metadata = [],
    ): void {
        if (! class_exists(ActivitylogServiceProvider::class)) {
            return;
        }

        activity($this->logName)
            ->withProperties($this->buildContext('manual_retry', [
                'communication_id' => $communicationId,
                'reason' => $reason,
                'metadata' => $metadata,
            ]))
            ->event(self::ACTION_MAP['manual_retry'])
            ->log('Communication manually retried');
    }

    public function recordCancel(
        string $communicationId,
        ?string $actorType,
        ?string $actorId,
        ?string $reason = null,
        array $metadata = [],
    ): void {
        if (! class_exists(ActivitylogServiceProvider::class)) {
            return;
        }

        activity($this->logName)
            ->withProperties($this->buildContext('cancel', [
                'communication_id' => $communicationId,
                'reason' => $reason,
                'metadata' => $metadata,
            ]))
            ->event(self::ACTION_MAP['cancel'])
            ->log('Communication cancelled');
    }

    public function recordSuppressionCreate(
        string $suppressionId,
        ?string $actorType,
        ?string $actorId,
        ?string $reason = null,
        array $metadata = [],
    ): void {
        if (! class_exists(ActivitylogServiceProvider::class)) {
            return;
        }

        activity($this->logName)
            ->withProperties($this->buildContext('suppression_create', [
                'suppression_id' => $suppressionId,
                'reason' => $reason,
                'metadata' => $metadata,
            ]))
            ->event(self::ACTION_MAP['suppression_create'])
            ->log('Suppression created');
    }

    public function recordSuppressionLift(
        string $suppressionId,
        ?string $actorType,
        ?string $actorId,
        ?string $reason = null,
        array $metadata = [],
    ): void {
        if (! class_exists(ActivitylogServiceProvider::class)) {
            return;
        }

        activity($this->logName)
            ->withProperties($this->buildContext('suppression_lift', [
                'suppression_id' => $suppressionId,
                'reason' => $reason,
                'metadata' => $metadata,
            ]))
            ->event(self::ACTION_MAP['suppression_lift'])
            ->log('Suppression lifted');
    }

    public function recordPayloadRedact(
        string $communicationId,
        ?string $actorType,
        ?string $actorId,
        ?string $reason = null,
        array $metadata = [],
    ): void {
        if (! class_exists(ActivitylogServiceProvider::class)) {
            return;
        }

        activity($this->logName)
            ->withProperties($this->buildContext('payload_redact', [
                'communication_id' => $communicationId,
                'reason' => $reason,
                'metadata' => $metadata,
            ]))
            ->event(self::ACTION_MAP['payload_redact'])
            ->log('Communication payload redacted');
    }

    public function recordWebhookReplay(
        string $communicationId,
        ?string $actorType,
        ?string $actorId,
        ?string $reason = null,
        array $metadata = [],
    ): void {
        if (! class_exists(ActivitylogServiceProvider::class)) {
            return;
        }

        activity($this->logName)
            ->withProperties($this->buildContext('webhook_replay', [
                'communication_id' => $communicationId,
                'reason' => $reason,
                'metadata' => $metadata,
            ]))
            ->event(self::ACTION_MAP['webhook_replay'])
            ->log('Webhook replayed');
    }

    private function buildContext(string $action, array $extra = []): array
    {
        return array_merge([
            'action' => $action,
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ], $extra);
    }
}
