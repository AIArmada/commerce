<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionAttributionMaintenance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use LogicException;

final readonly class DetachConversionAttribution implements ConversionAttributionMaintenance
{
    public function detachAttribution(string $attributionId): void
    {
        if ((new AffiliateConversion)->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Detaching conversion attribution requires an active removal transaction.');
        }

        AffiliateConversion::query()->where('affiliate_attribution_id', $attributionId)->update(['affiliate_attribution_id' => null]);
    }
}
