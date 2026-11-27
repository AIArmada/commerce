<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Reporting\Api;

interface DashboardStats
{
    /**
     * @return array{total: int, delivered: int, in_transit: int, problems: int, pending: int, returns: int}
     */
    public function calculateOrderStats(): array;
}
