<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Data;

use Carbon\CarbonImmutable;

final readonly class PassData
{
    public function __construct(
        public string $pass_no,
        public ?string $id = null,
        public ?string $qr_code = null,
        public ?string $barcode = null,
        public string $status = 'pending',
        public ?string $ticket_type_name = null,
        public ?CarbonImmutable $issued_at = null,
        public ?CarbonImmutable $activated_at = null,
        public ?CarbonImmutable $cancelled_at = null,
        public ?CarbonImmutable $revoked_at = null,
        public ?CarbonImmutable $voided_at = null,
        public ?CarbonImmutable $used_at = null,
        public ?CarbonImmutable $expired_at = null,
        public ?PassHolderData $holder = null,
        public ?array $metadata = null,
    ) {}
}
