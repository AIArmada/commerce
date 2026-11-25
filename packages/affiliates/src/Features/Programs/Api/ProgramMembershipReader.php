<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface ProgramMembershipReader
{
    public function programExists(string $programId): bool;

    public function find(string $affiliateId, string $programId): ?MembershipRecord;
}
