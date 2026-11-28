<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Api;

interface BatchExportRows
{
    /** @return iterable<int, array<int, mixed>> */
    public function rows(?string $status, bool $expiringOnly, int $expiringWithinDays): iterable;
}
