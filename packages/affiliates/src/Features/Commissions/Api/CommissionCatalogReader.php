<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Commissions\Api;

interface CommissionCatalogReader
{
    /** @return list<array{subject_type: string, subject_key: string, title: string, url: string, amount_minor: null, currency: null, context: array<string, mixed>}> */
    public function fallbackSubjects(string $programId, int $max): array;

    /** @return array<int, array<string, mixed>> */
    public function activePromotions(string $programId): array;
}
