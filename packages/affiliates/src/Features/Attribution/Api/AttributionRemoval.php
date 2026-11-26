<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

interface AttributionRemoval
{
    /** @param list<string> $attributionIds */
    public function deleteMany(array $attributionIds): void;

    public function deleteForAffiliate(string $affiliateId): void;
}
