<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Data;

use DateTimeImmutable;

final readonly class VoucherUsageData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $id,
        public string $voucherId,
        public int $discountAmount,
        public string $currency,
        public ?string $channel,
        public ?string $redeemedByType,
        public ?string $redeemedById,
        public DateTimeImmutable $usedAt,
        public ?array $metadata,
        public ?string $notes,
    ) {}
}
