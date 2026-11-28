<?php

declare(strict_types=1);

namespace AIArmada\Tax\Services\ZoneResolver;

use AIArmada\Tax\Services\ZoneResolver\TaxZoneResolverInterface;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Queries\TaxOwnerScope;

final class ZoneIdResolver implements TaxZoneResolverInterface
{
    public function resolve(?string $zoneId, array $context): ?TaxZone
    {
        if ($zoneId === null) {
            return null;
        }

        return TaxOwnerScope::apply(TaxZone::query(), $context)
            ->whereKey($zoneId)
            ->first();
    }
}
