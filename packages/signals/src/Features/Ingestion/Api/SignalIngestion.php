<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Ingestion\Api;

interface SignalIngestion
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $trackedPropertyId, array $payload, bool $trusted): IngestedSignalEvent;
}
