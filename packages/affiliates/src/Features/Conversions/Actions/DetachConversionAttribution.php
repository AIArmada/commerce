<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionAttributionMaintenance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use LogicException;

final readonly class DetachConversionAttribution implements ConversionAttributionMaintenance
{
    public function detachAttribution(string $attributionId, int $connectionIdentity): void
    {
        $connection = (new AffiliateConversion)->getConnection();

        if (spl_object_id($connection) !== $connectionIdentity || $connection->transactionLevel() === 0) {
            throw new LogicException('Detaching conversion attribution requires an active removal transaction.');
        }

        $query = AffiliateConversion::query()->where('affiliate_attribution_id', $attributionId);
        $all = AffiliateConversion::withoutGlobalScopes()->where('affiliate_attribution_id', $attributionId);

        if ($query->count() !== $all->count()) {
            throw new LogicException('Attribution removal cannot detach conversions outside the current owner scope.');
        }

        $query->update(['affiliate_attribution_id' => null]);
    }
}
