<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Cart\Cart;

interface AffiliateLookup
{
    public function findByCode(string $code): ?Affiliate;

    public function findByDefaultVoucherCode(string $voucherCode): ?Affiliate;

    public function findById(string $id): ?Affiliate;

    public function findActiveAffiliateByCookie(string $cookieValue): ?Affiliate;

    public function findActiveAttributionByCookie(string $cookieValue): ?AffiliateAttribution;

    public function findAttachedAttribution(Cart $cart): ?AffiliateAttribution;
}
