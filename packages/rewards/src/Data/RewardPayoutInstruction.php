<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Data;

final readonly class RewardPayoutInstruction
{
    /**
     * @param array<string, mixed> $payeeSnapshot
     * @param array<string, mixed>|null $evidence
     */
    public function __construct(
        public string $id,
        public string $awardId,
        public string $programId,
        public string $payeeId,
        public string $currency,
        public int $amountMinor,
        public int $feeMinor,
        public string $feePayer,
        public string $processor,
        public string $operationKey,
        public string $firstReference,
        public array $payeeSnapshot,
        public string $status,
        public ?string $externalPayoutId = null,
        public ?string $externalReferenceId = null,
        public ?string $externalProvider = null,
        public ?string $providerReference = null,
        public ?string $failureCode = null,
        public ?array $evidence = null,
    ) {}
}
