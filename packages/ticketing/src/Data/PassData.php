<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class PassData extends Data
{
    public function __construct(
        public readonly string $pass_no,
        public readonly ?string $qr_code = null,
        public readonly ?string $barcode = null,
        public readonly string $status = 'pending',
        public readonly ?CarbonImmutable $issued_at = null,
        public readonly ?CarbonImmutable $activated_at = null,
        public readonly ?CarbonImmutable $cancelled_at = null,
        public readonly ?CarbonImmutable $revoked_at = null,
        public readonly ?CarbonImmutable $voided_at = null,
        public readonly ?CarbonImmutable $used_at = null,
        public readonly ?CarbonImmutable $expired_at = null,
        public readonly ?PassHolderData $holder = null,
        public readonly ?array $metadata = null,
    ) {}
}
