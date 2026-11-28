<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Traceability\Api;

interface BatchIdentity
{
    public function matchesWritableBatch(string $batchId, string $type, string $id, ?string $locationId): bool;
}
