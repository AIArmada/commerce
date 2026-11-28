<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Api;

interface ScheduledCommunicationLifecycle
{
    public function countDue(): int;

    /** @return iterable<int, string> */
    public function dueIds(int $batchSize): iterable;

    public function dispatch(string $id): bool;

    public function countExpired(): int;

    /** @return iterable<int, string> */
    public function expiredIds(): iterable;

    public function expire(string $id): bool;
}
