<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Identity\Queries;

final readonly class ReadAffiliateCreatives
{
    public function __construct(private AffiliateLookupQuery $affiliates) {}

    /** @return array<array-key, mixed>|null */
    public function findByCode(string $code): ?array
    {
        $affiliate = $this->affiliates->findByCode($code);

        if ($affiliate === null) {
            return null;
        }

        $creatives = $affiliate->metadata['creatives'] ?? [];

        return is_array($creatives) ? $creatives : [];
    }
}
