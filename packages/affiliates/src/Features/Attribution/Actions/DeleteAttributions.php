<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Actions;

use AIArmada\Affiliates\Features\Attribution\Api\AttributionRemoval;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateAttribution;
use AIArmada\Affiliates\Features\Attribution\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionAttributionMaintenance;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final readonly class DeleteAttributions implements AttributionRemoval
{
    public function __construct(private ConversionAttributionMaintenance $conversions, private AffiliateAccessGuard $affiliates) {}

    public function deleteMany(array $attributionIds): void
    {
        if ($attributionIds === []) {
            return;
        }

        $this->remove(AffiliateAttribution::query()->whereIn('id', $attributionIds));
    }

    public function deleteForAffiliate(string $affiliateId): void
    {
        $this->affiliates->assertCanWrite($affiliateId, spl_object_id((new AffiliateAttribution)->getConnection()));
        $this->remove(AffiliateAttribution::query()->where('affiliate_id', $affiliateId));
    }

    /** @param Builder<AffiliateAttribution> $query */
    private function remove(Builder $query): void
    {
        $connection = $query->getModel()->getConnection();

        $this->affiliates->assertConnection(spl_object_id($connection));

        if ($connection !== (new AffiliateTouchpoint)->getConnection()) {
            throw new LogicException('Attribution removal requires a shared database connection.');
        }

        $connection->transaction(function () use ($query): void {
            (clone $query)->lockForUpdate()->chunkById(100, function ($attributions): void {
                foreach ($attributions as $attribution) {
                    AffiliateTouchpoint::query()->where('affiliate_attribution_id', $attribution->getKey())->delete();
                    $this->conversions->detachAttribution((string) $attribution->getKey(), spl_object_id($attribution->getConnection()));

                    if (! $attribution->delete()) {
                        throw new LogicException('The affiliate attribution could not be deleted.');
                    }
                }
            });
        }, attempts: 3);
    }
}
