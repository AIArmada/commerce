<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Api;

interface WebhookReplay
{
    public function count(bool $force, ?string $provider, ?string $communicationId, ?string $deliveryId): int;

    /** @return iterable<int, string> */
    public function ids(bool $force, ?string $provider, ?string $communicationId, ?string $deliveryId, int $batchSize): iterable;

    /** @return 'replayed'|'skipped'|'failed' */
    public function replay(string $eventId, bool $force): string;
}
