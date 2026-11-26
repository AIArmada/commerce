<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Actions;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReference;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateVoucherIssuer;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateVoucherProvisioning;
use AIArmada\Affiliates\Features\Identity\Queries\AffiliateLifecycleQuery;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\CommerceSupport\Support\OwnerContext;
use LogicException;

final readonly class ProvisionAffiliateVoucher implements AffiliateVoucherProvisioning
{
    public function __construct(private AffiliateLifecycleQuery $affiliates) {}

    public function provision(AffiliateLifecycleReference $reference, AffiliateVoucherIssuer $issuer, bool $requireActive, bool $setDefault): void
    {
        $connection = (new Affiliate)->getConnection();

        if ((string) $connection->getName() !== $issuer->connectionName()) {
            throw new LogicException('Affiliate voucher provisioning requires a shared database connection.');
        }

        $connection->transaction(function () use ($reference, $issuer, $requireActive, $setDefault): void {
            $affiliate = Affiliate::query()->withoutOwnerScope()->lockForUpdate()->find($reference->id);

            if ($affiliate === null) {
                return;
            }

            $record = $this->affiliates->project($affiliate, $reference);

            if ($requireActive && ! $record->active) {
                return;
            }

            $owner = OwnerContext::fromTypeAndIdOrFail($record->reference->ownerType, $record->reference->ownerId);

            OwnerContext::withOwner($owner, function () use ($affiliate, $record, $issuer, $setDefault): void {
                $code = $issuer->issue($record);

                if ($code !== null && $setDefault && ! $affiliate->update(['default_voucher_code' => $code])) {
                    throw new LogicException('The default affiliate voucher code could not be persisted.');
                }
            });
        }, attempts: 3);
    }
}
