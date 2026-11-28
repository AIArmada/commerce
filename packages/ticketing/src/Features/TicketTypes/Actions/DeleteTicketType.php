<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Features\TicketTypes\Actions;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Ticketing\Models\TicketType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

final readonly class DeleteTicketType
{
    public function handle(TicketType $record, ?Model $host = null): bool
    {
        return $record->getConnection()->transaction(function () use ($record, $host): bool {
            $type = OwnerUiScope::apply(TicketType::query(), includeGlobal: false)
                ->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($host !== null && ($type->ticketable_type !== $host->getMorphClass() || (string) $type->ticketable_id !== (string) $host->getKey())) {
                throw new AuthorizationException('The ticket type does not belong to this host.');
            }

            return (bool) $type->delete();
        });
    }
}
