<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Models\CommunicationAttempt;
use Carbon\CarbonImmutable;

final class CompleteDeliveryAttemptAction
{
    public function handle(
        string $attemptId,
        ?string $providerMessageId = null,
        ?int $durationMs = null,
        array $responsePayload = [],
    ): CommunicationAttempt {
        $attempt = CommunicationAttempt::query()->findOrFail($attemptId);

        $attempt->provider_message_id = $providerMessageId ?? $attempt->provider_message_id;
        $attempt->duration_ms = $durationMs;
        $attempt->response_payload = $responsePayload;
        $attempt->responded_at = CarbonImmutable::now();
        $attempt->save();

        return $attempt;
    }
}
