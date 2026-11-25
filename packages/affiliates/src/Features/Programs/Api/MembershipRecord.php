<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

use AIArmada\Affiliates\Enums\MembershipStatus;

final readonly class MembershipRecord
{
    public function __construct(
        public string $id,
        public string $affiliateId,
        public string $programId,
        public ?string $tierId,
        public MembershipStatus $status,
        public ?string $appliedAt,
        public ?string $approvedAt,
        public bool $active,
        public bool $created = false,
    ) {}
}
