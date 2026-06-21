<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\Communications\Models\CommunicationBatch;
use Carbon\CarbonImmutable;

final class CreateCommunicationBatchAction
{
    public function handle(
        string $category,
        ?string $name = null,
        ?string $purpose = null,
        ?string $scheduledAt = null,
        ?string $expiresAt = null,
        ?string $idempotencyKey = null,
        ?array $metadata = null,
    ): CommunicationBatch {
        $batch = new CommunicationBatch;
        $batch->name = $name ?? $category;
        $batch->purpose = $purpose ?? $name ?? $category;
        $batch->category = $category;
        $batch->status = 'draft';
        $batch->idempotency_key = $idempotencyKey;
        $batch->scheduled_at = $scheduledAt !== null ? CarbonImmutable::parse($scheduledAt) : null;
        $batch->expires_at = $expiresAt !== null ? CarbonImmutable::parse($expiresAt) : null;
        $batch->metadata = $metadata;
        $batch->save();

        return $batch;
    }
}
