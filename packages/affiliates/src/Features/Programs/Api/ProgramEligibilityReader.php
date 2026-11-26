<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface ProgramEligibilityReader
{
    public function canJoin(string $affiliateId, string $programId): bool;
}
