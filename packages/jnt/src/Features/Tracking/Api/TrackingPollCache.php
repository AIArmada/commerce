<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Api;

interface TrackingPollCache
{
    /** @return array<string, mixed>|null */
    public function get(string $cacheKey): ?array;

    /** @param array<string, mixed> $payload */
    public function put(string $cacheKey, array $payload, int $ttlSeconds): void;
}
