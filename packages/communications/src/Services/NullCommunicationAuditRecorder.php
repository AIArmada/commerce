<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\CommunicationAuditRecorder;

final class NullCommunicationAuditRecorder implements CommunicationAuditRecorder
{
    public function recordManualRetry(
        string $communicationId,
        ?string $actorType,
        ?string $actorId,
        ?string $reason = null,
        array $metadata = [],
    ): void {}

    public function recordCancel(
        string $communicationId,
        ?string $actorType,
        ?string $actorId,
        ?string $reason = null,
        array $metadata = [],
    ): void {}

    public function recordSuppressionCreate(
        string $suppressionId,
        ?string $actorType,
        ?string $actorId,
        ?string $reason = null,
        array $metadata = [],
    ): void {}

    public function recordSuppressionLift(
        string $suppressionId,
        ?string $actorType,
        ?string $actorId,
        ?string $reason = null,
        array $metadata = [],
    ): void {}

    public function recordPayloadRedact(
        string $communicationId,
        ?string $actorType,
        ?string $actorId,
        ?string $reason = null,
        array $metadata = [],
    ): void {}

    public function recordWebhookReplay(
        string $communicationId,
        ?string $actorType,
        ?string $actorId,
        ?string $reason = null,
        array $metadata = [],
    ): void {}
}
