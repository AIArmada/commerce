<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\Passes\Persistence;

use AIArmada\Ticketing\Features\Passes\Models\Pass;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class PassDetacher
{
    public function detachFromTicketType(string $ticketTypeId, ?string $ownerType, string | int | null $ownerId): void
    {
        $passes = Pass::query()
            ->withoutOwnerScope()
            ->where('ticket_type_id', $ticketTypeId)
            ->get();

        foreach ($passes as $pass) {
            $passType = $pass->getAttribute('owner_type');
            $passId = $pass->getAttribute('owner_id');

            if (($passType === null) !== ($passId === null)
                || $passType !== $ownerType
                || (string) $passId !== (string) $ownerId) {
                throw new AuthorizationException('The ticket type has passes belonging to another owner.');
            }
        }

        Pass::query()
            ->withoutOwnerScope()
            ->where('ticket_type_id', $ticketTypeId)
            ->update(['ticket_type_id' => null]);
    }
}
