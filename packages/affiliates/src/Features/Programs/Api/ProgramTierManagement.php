<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface ProgramTierManagement
{
    public function upgrade(string $affiliateId, string $programId, string $tierId): MembershipRecord;

    public function processUpgrades(string $programId): int;
}
