<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

interface MembershipReviewApi
{
    public function approve(string $membershipId, ?string $approvedBy = null): bool;

    public function reject(string $membershipId): bool;
}
