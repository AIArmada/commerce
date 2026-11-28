<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Queries;

use AIArmada\Ticketing\Data\PassData;
use AIArmada\Ticketing\Data\PassHolderData;
use AIArmada\Ticketing\Features\Passes\Models\Pass;

final readonly class MapPassData
{
    public function handle(Pass $pass): PassData
    {
        $status = $pass->status;

        if (is_object($status) && method_exists($status, 'getValue')) {
            $status = $status->getValue();
        } elseif (is_object($status) && method_exists($status, 'getMorphClass')) {
            $status = $status::getMorphClass();
        }

        $ticketType = $pass->relationLoaded('ticketType') ? $pass->ticketType : null;
        $ticketTypeName = is_object($ticketType) ? $ticketType->getAttribute('name') : null;

        return new PassData(
            pass_no: $pass->pass_no,
            id: (string) $pass->getKey(),
            qr_code: $pass->qr_code,
            barcode: $pass->barcode,
            status: (string) $status,
            ticket_type_name: is_string($ticketTypeName) ? $ticketTypeName : null,
            issued_at: $pass->issued_at,
            activated_at: $pass->activated_at,
            cancelled_at: $pass->cancelled_at,
            revoked_at: $pass->revoked_at,
            voided_at: $pass->voided_at,
            used_at: $pass->used_at,
            expired_at: $pass->expired_at,
            holder: $pass->relationLoaded('holder') && $pass->holder !== null
                ? new PassHolderData(id: (string) $pass->holder->getKey(), holder_type: $pass->holder->holder_type, holder_id: $pass->holder->holder_id, name: $pass->holder->name, email: $pass->holder->email)
                : null,
            metadata: $pass->metadata,
            pass_type: $pass->getMorphClass(),
            ticketable_type: $pass->ticketable_type,
            ticketable_id: (string) $pass->ticketable_id,
            registration_type: $pass->registration_type,
            registration_id: $pass->registration_id !== null ? (string) $pass->registration_id : null,
            occurrence_id: $pass->occurrence_id !== null ? (string) $pass->occurrence_id : null,
            session_id: $pass->session_id !== null ? (string) $pass->session_id : null,
        );
    }
}
