<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface ProgramEnrollment
{
    public function join(string $affiliateId, string $programId): MembershipRecord;

    public function leave(string $affiliateId, string $programId): bool;
}
