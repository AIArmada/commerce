<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Api;

use DateTimeInterface;

interface DeliveryRetention
{
    /** @return array{attempts: int, events: int} */
    public function prune(DateTimeInterface $before, bool $dryRun): array;
}
