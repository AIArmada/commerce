<?php

declare(strict_types=1);

namespace AIArmada\Membership\Data;

use Carbon\CarbonImmutable;

final readonly class MembershipApplicationData
{
    /** @param array<string, mixed>|null $meta */
    public function __construct(
        public string $id,
        public string $subjectType,
        public string $subjectId,
        public string $applicantId,
        public string $status,
        public string $justification,
        public ?string $grantedRole,
        public ?string $reviewerId,
        public ?string $reviewerNote,
        public ?CarbonImmutable $reviewedAt,
        public ?array $meta,
    ) {}
}
