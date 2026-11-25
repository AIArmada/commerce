<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateRemoval
{
    public function delete(string $affiliateId): bool;
}
