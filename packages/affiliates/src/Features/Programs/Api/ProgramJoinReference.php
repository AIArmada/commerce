<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReference;

final readonly class ProgramJoinReference
{
    public function __construct(
        public AffiliateLifecycleReference $affiliate,
        public string $programId,
        public ?string $programOwnerType,
        public ?string $programOwnerId,
        public string $membershipId,
        public string $membershipAffiliateId,
        public string $membershipProgramId,
        public ?string $tierId,
    ) {}
}
