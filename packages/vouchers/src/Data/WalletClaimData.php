<?php

declare(strict_types=1);

namespace AIArmada\Vouchers\Data;

final readonly class WalletClaimData
{
    /** @param array<string, mixed>|null $metadata */
    public function __construct(
        public string $id,
        public string $voucherId,
        public string $holderType,
        public string $holderId,
        public ?array $metadata,
        public bool $created,
    ) {}
}
