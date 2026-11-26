<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Api;

interface AffiliateReportingReader
{
    public function available(): bool;

    public function nameFor(string $affiliateId, ?string $ownerType, ?string $ownerId, int $connectionIdentity): ?string;

    /** @return array<string, string> */
    public function codeOptions(): array;
}
