<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

interface CommunicationRecorder
{
    public function markSending(string $communicationId, string $deliveryId): void;

    public function markSent(string $communicationId, string $deliveryId, array $result): void;

    public function markFailed(string $communicationId, string $deliveryId, string $failureMessage): void;
}
