<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateDirectory;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateRecord;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;

final readonly class AffiliateDirectoryQuery implements AffiliateDirectory
{
    public function __construct(private AffiliateLookupQuery $lookup) {}

    public function findByCode(string $code): ?AffiliateRecord
    {
        return $this->project($this->lookup->findByCode($code));
    }

    public function findByDefaultVoucherCode(string $voucherCode): ?AffiliateRecord
    {
        return $this->project($this->lookup->findByDefaultVoucherCode($voucherCode));
    }

    public function findById(string $id): ?AffiliateRecord
    {
        return $this->project($this->lookup->findById($id));
    }

    public function findActiveAffiliateByCookie(string $cookieValue): ?AffiliateRecord
    {
        return $this->project($this->lookup->findActiveAffiliateByCookie($cookieValue));
    }

    private function project(?Affiliate $affiliate): ?AffiliateRecord
    {
        return $affiliate === null ? null : new AffiliateRecord(
            id: (string) $affiliate->getKey(),
            code: $affiliate->code,
            name: $affiliate->name,
            active: $affiliate->isActive(),
            canBeAttributed: $affiliate->canBeAttributed(),
            defaultVoucherCode: $affiliate->default_voucher_code,
        );
    }
}
