<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

use AIArmada\Communications\Data\CommunicationContextData;
use AIArmada\Communications\Models\Communication;

interface CommunicationRecorder
{
    public function createCommunication(CommunicationContextData $context): Communication;

    public function markSending(string $communicationId, string $deliveryId): void;

    public function markSent(string $communicationId, string $deliveryId, array $result): void;

    public function markFailed(string $communicationId, string $deliveryId, string $failureMessage): void;

    public function cancelCommunication(string $communicationId): void;
}
