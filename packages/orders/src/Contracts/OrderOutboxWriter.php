<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

use Closure;

interface OrderOutboxWriter
{
    public function stage(string $orderId, string $eventClass, string $transactionId, string $gateway): ?string;

    public function suppressForOrder(string $orderId, string $reason): int;

    /** @param  list<string|null>  $ids */
    public function dispatchReplayable(string $orderId, array $ids, Closure $dispatch): void;
}
