<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Data;

use AIArmada\Ticketing\Models\Pass;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class PassData extends Data
{
    public function __construct(
        public readonly string $pass_no,
        public readonly ?string $id = null,
        public readonly ?string $qr_code = null,
        public readonly ?string $barcode = null,
        public readonly string $status = 'pending',
        public readonly ?string $ticket_type_name = null,
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

    public static function fromPass(Pass $pass): self
    {
        $status = $pass->status;

        if (is_object($status) && method_exists($status, 'getValue')) {
            $status = $status->getValue();
        } elseif (is_object($status) && method_exists($status, 'getMorphClass')) {
            $status = $status::getMorphClass();
        }

        return new self(
            pass_no: $pass->pass_no,
            id: (string) $pass->getKey(),
            qr_code: $pass->qr_code,
            barcode: $pass->barcode,
            status: (string) $status,
            ticket_type_name: $pass->relationLoaded('ticketType')
                ? $pass->ticketType?->name
                : null,
            issued_at: $pass->issued_at,
            activated_at: $pass->activated_at,
            cancelled_at: $pass->cancelled_at,
            revoked_at: $pass->revoked_at,
            voided_at: $pass->voided_at,
            used_at: $pass->used_at,
            expired_at: $pass->expired_at,
            metadata: $pass->metadata,
        );
    }
}
