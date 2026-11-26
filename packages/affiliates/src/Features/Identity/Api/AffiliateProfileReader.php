<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateProfileReader
{
    public function morphType(): string;

    public function matchesMorphType(?string $type): bool;

    public function find(string $id, int $connectionIdentity, bool $includeGlobal = false): ?AffiliateProfile;

    public function lock(string $id, int $connectionIdentity, bool $includeGlobal = false): ?AffiliateProfile;
}
