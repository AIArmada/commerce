<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateDirectory
{
    public function codeAvailable(string $code, ?string $ignoreAffiliateId = null): bool;

    public function findByCode(string $code): ?AffiliateRecord;

    public function findByDefaultVoucherCode(string $voucherCode): ?AffiliateRecord;

    public function findById(string $id): ?AffiliateRecord;

    public function findActiveAffiliateByCookie(string $cookieValue): ?AffiliateRecord;
}
