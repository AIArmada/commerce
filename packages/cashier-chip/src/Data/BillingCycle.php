<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Data;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class BillingCycle
{
    public const INTERVALS = ['day', 'week', 'month', 'year'];

    public function __construct(public string $interval = 'month', public int $count = 1)
    {
        if (! in_array($interval, self::INTERVALS, true)) {
            throw new InvalidArgumentException(
                'Billing interval must be one of: ' . implode(', ', self::INTERVALS) . '.'
            );
        }

        if ($count < 1) {
            throw new InvalidArgumentException('Billing interval count must be a positive integer.');
        }
    }

    public function advance(DateTimeInterface $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)->add($this->interval, $this->count);
    }
}
