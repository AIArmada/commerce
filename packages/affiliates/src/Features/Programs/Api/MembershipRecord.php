<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Api;

final readonly class MembershipRecord
{
    public function __construct(
        public string $id,
        public string $affiliateId,
        public string $programId,
        public ?string $tierId,
        public string $status,
        public ?string $appliedAt,
        public ?string $approvedAt,
        public bool $active,
        public bool $created = false,
    ) {}

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
