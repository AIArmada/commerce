<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

use Carbon\CarbonImmutable;

final readonly class AttributionConversionRecord
{
    /**
     * @param array<string, mixed>|null $commissionOverride
     * @param list<array<string, mixed>>|null $uplineLevels
     */
    public function __construct(
        public string $id,
        public string $affiliateId,
        public string $affiliateCode,
        public ?string $affiliateLinkId,
        public ?string $affiliateProgramId,
        public ?string $subjectType,
        public ?string $subjectKey,
        public ?string $subjectId,
        public ?string $subjectInstance,
        public ?string $subjectTitleSnapshot,
        public ?string $voucherCode,
        public ?array $commissionOverride,
        public ?array $uplineLevels,
        public ?string $channel,
        public ?string $origin,
        public ?string $sharerUserId,
        public ?string $ownerType,
        public ?string $ownerId,
        public ?CarbonImmutable $lastReferredAt,
    ) {}

    public function provenance(string $key): ?string
    {
        return match ($key) {
            'subject_key' => $this->subjectKey,
            'origin' => $this->origin,
            'affiliate_link_id' => $this->affiliateLinkId,
            'affiliate_program_id' => $this->affiliateProgramId,
            'sharer_user_id' => $this->sharerUserId,
            default => null,
        };
    }
}
