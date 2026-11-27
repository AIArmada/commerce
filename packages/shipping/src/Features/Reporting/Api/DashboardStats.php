<?php

declare(strict_types=1);

namespace AIArmada\Shipping\Features\Reporting\Api;

interface DashboardStats
{
    /**
     * @return array{total: int, pending: int, inTransit: int, deliveredToday: int, exceptions: int, pendingReturns: int}
     */
    public function getAllStats(): array;
}
