<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateLifecycleReader
{
    public function find(AffiliateLifecycleReference $reference): ?AffiliateLifecycleRecord;

    public function findById(string $id): ?AffiliateLifecycleRecord;
}
