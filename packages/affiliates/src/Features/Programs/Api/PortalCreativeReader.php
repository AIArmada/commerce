<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface PortalCreativeReader
{
    /** @return list<array<string, mixed>> */
    public function assets(string $affiliateId): array;
}
