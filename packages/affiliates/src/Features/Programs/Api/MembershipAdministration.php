<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface MembershipAdministration
{
    /** @param array<string, mixed> $data */
    public function create(string $affiliateId, string $programId, array $data): MembershipRecord;

    /** @param array<string, mixed> $data */
    public function update(string $membershipId, array $data): MembershipRecord;

    public function delete(string $membershipId): void;

    public function suspend(string $membershipId): bool;
}
