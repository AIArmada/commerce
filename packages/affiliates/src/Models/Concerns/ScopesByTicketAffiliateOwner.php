<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models\Concerns;

use AIArmada\Affiliates\Models\AffiliateSupportTicket;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

trait ScopesByTicketAffiliateOwner
{
    protected static string $ticketForeignKey = 'ticket_id';

    protected static function bootScopesByTicketAffiliateOwner(): void
    {
        static::creating(function ($model): void {
            static::guardTicketForeignKey($model);
        });

        static::updating(function ($model): void {
            static::guardTicketForeignKey($model);
        });

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

    protected static function guardTicketForeignKey(object $model): void
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return;
        }

        $foreignKey = static::$ticketForeignKey;
        $ticketId = $model->{$foreignKey} ?? null;

        if ($ticketId === null) {
            return;
        }

        if (! AffiliateSupportTicket::query()->whereKey($ticketId)->exists()) {
            throw new AuthorizationException('Cross-tenant support ticket reference is not allowed.');
        }
    }
}
