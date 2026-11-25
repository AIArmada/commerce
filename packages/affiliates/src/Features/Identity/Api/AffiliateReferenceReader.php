<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateReferenceReader
{
    public function exists(string $id): bool;

    /** @return array<string, string> */
    public function options(string $search = '', int $limit = 50): array;

    public function label(string $id): ?string;

    public function forOwner(string $ownerType, string $ownerId): ?AffiliateAccountSnapshot;
}
