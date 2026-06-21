<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

interface IdempotencyLock
{
    public function acquire(string $key, int $ttlSeconds): bool;

    public function release(string $key): void;

    public function exists(string $key): bool;
}
