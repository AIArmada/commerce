<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models\Concerns;

use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use Illuminate\Database\Eloquent\Builder;

trait ScopesByTicketAffiliateOwner
{
    protected static string $ticketForeignKey = 'ticket_id';

    protected static function bootScopesByTicketAffiliateOwner(): void
    {
        static::addGlobalScope('ticket_affiliate_owner', function (Builder $builder): void {
            if (! (bool) config('affiliates.owner.enabled', false)) {
                return;
            }

            $foreignKey = static::$ticketForeignKey;

            $builder->whereIn(
                $builder->getModel()->qualifyColumn($foreignKey),
                AffiliateSupportTicket::query()->select('id')
            );
        });
    }
}
