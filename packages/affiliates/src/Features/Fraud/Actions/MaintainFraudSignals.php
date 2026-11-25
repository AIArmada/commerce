<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Actions;

use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalMaintenance;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use LogicException;

final readonly class MaintainFraudSignals implements FraudSignalMaintenance
{
    public function assertConnection(?string $name, int $instanceId): void
    {
        $connection = (new AffiliateFraudSignal)->getConnection();

        if ($connection->getName() !== $name || spl_object_id($connection) !== $instanceId) {
            throw new LogicException('Fraud signal writes must use the shared affiliate transaction connection.');
        }
    }

    public function deleteForAffiliate(string $affiliateId): void
    {
        if ((bool) config('affiliates.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Affiliate::class, $affiliateId);
        }

        AffiliateFraudSignal::query()->where('affiliate_id', $affiliateId)->delete();
    }
}
