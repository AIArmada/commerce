<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

interface CreativeCleanupQueue
{
    /** @param array<string, mixed> $attributes */
    public function enqueue(string $mediaClass, array $attributes, int $connectionIdentity): void;
}
