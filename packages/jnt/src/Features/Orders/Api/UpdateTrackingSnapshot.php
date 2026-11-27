<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Orders\Api;

use DateTimeImmutable;

interface UpdateTrackingSnapshot
{
    /** @param array<string, mixed> $attributes */
    public function handle(string $orderId, array $attributes, ?DateTimeImmutable $scanTime): void;
}
