<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Api;

interface StockIdentity
{
    /** @return array{type: string, id: string, location_id: string}|null */
    public function lockWritableLevel(string $levelId): ?array;

    public function matchesWritableLevel(string $levelId, string $type, string $id, string $locationId): bool;
}
