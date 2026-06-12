<?php

declare(strict_types=1);

namespace AIArmada\Events\Data;

use AIArmada\Events\Models\EventPass;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class PassData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $pass_no,
        public readonly string | null | Optional $qr_code,
        public readonly string | null | Optional $barcode,
        public readonly string $status,
        public readonly string | null | Optional $ticket_type_name,
        public readonly CarbonImmutable | null | Optional $issued_at,
        public readonly CarbonImmutable | null | Optional $activated_at,
        public readonly CarbonImmutable | null | Optional $used_at,
    ) {}

    public static function fromPass(EventPass $pass): self
    {
        $ticketTypeName = null;
        if ($pass->relationLoaded('ticketType') && $pass->ticketType) {
            $ticketTypeName = $pass->ticketType->name;
        }

        return new self(
            id: $pass->id,
            pass_no: $pass->pass_no,
            qr_code: $pass->qr_code,
            barcode: $pass->barcode,
            status: $pass->status,
            ticket_type_name: $ticketTypeName,
            issued_at: $pass->issued_at,
            activated_at: $pass->activated_at,
            used_at: $pass->used_at,
        );
    }
}
