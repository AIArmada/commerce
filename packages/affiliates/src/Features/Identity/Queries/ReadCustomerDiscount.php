<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

use AIArmada\Affiliates\Features\Identity\Api\CustomerDiscount;
use AIArmada\Affiliates\Features\Identity\Api\CustomerDiscountReader;
use AIArmada\Affiliates\Features\Upline\Api\RankDirectory;

final readonly class ReadCustomerDiscount implements CustomerDiscountReader
{
    public function __construct(private AffiliateLookupQuery $lookup, private RankDirectory $ranks) {}

    public function find(string $affiliateId): ?CustomerDiscount
    {
        $affiliate = $this->lookup->findById($affiliateId);

        if ($affiliate === null || ! $affiliate->isActive()) {
            return null;
        }

        $discount = $affiliate->metadata['customer_discount'] ?? null;

        if (! is_array($discount)) {
            $discount = $this->ranks->customerDiscount($affiliate->rank_id);
        }

        if ($discount === null) {
            return null;
        }

        $value = filter_var($discount['value'] ?? null, FILTER_VALIDATE_INT);
        $type = $discount['type'] ?? 'percentage';

        if (! in_array($type, ['percentage', 'fixed'], true) || $value === false || $value <= 0 || ($type === 'percentage' && $value > 10000)) {
            return null;
        }

        return new CustomerDiscount((string) $affiliate->getKey(), $affiliate->code, $affiliate->name, $type, $value);
    }
}
