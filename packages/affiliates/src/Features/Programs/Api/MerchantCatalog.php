<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface MerchantCatalog
{
    public function snapshotById(string $programId): ?ProgramSnapshot;

    public function previewById(string $programId): ProgramSnapshot;

    /** @return list<string> */
    public function mirrorableProgramIds(int $limit = 100): array;

    /** @return list<array{program_id: string, name: string, slug: string, status: string, currency: string, cookie_days: int}> */
    public function availablePrograms(): array;
}
