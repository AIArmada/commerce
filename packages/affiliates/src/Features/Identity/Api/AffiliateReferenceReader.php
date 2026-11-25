<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateReferenceReader
{
    public function exists(string $id): bool;

    public function forOwner(string $ownerType, string $ownerId): ?AffiliateAccountSnapshot;
}
