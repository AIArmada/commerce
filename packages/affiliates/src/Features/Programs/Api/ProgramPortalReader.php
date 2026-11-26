<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface ProgramPortalReader
{
    public function programExists(string $programId): bool;

    /** @return list<PortalProgramRecord> */
    public function programs(string $affiliateId): array;
}
