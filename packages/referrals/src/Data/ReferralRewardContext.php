<?php

declare(strict_types=1);

namespace AIArmada\Referrals\Data;

final readonly class ReferralRewardContext
{
    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $inviteId,
        public string $programId,
        public string $side,
        public ?string $beneficiaryType,
        public ?string $beneficiaryId,
        public string $driver,
        public ?int $amountMinor,
        public ?int $points,
        public ?string $currency,
        public array $spec,
        public string $idempotencyKey,
        public ?string $externalReference,
        public array $metadata,
        public ?string $qualificationBusinessKey,
    ) {}
}
