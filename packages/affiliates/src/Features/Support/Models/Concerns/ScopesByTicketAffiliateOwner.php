<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Support\Models\Concerns;

use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Support\Models\AffiliateSupportTicket;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait ScopesByTicketAffiliateOwner
{
    protected static string $ticketForeignKey = 'ticket_id';

    protected static function bootScopesByTicketAffiliateOwner(): void
    {
        static::creating(function (Model $model): void {
            static::guardTicketForeignKey($model);
        });

        static::updating(function (Model $model): void {
            static::guardTicketForeignKey($model);
        });

        static::deleting(function (Model $model): void {
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

    protected static function guardTicketForeignKey(Model $model): void
    {
        if (! (bool) config('affiliates.owner.enabled', false)) {
            return;
        }

        $foreignKey = static::$ticketForeignKey;
        $ticketIds = [$model->getAttribute($foreignKey)];

        if ($model->exists) {
            $ticketIds[] = $model->getRawOriginal($foreignKey);
        }

        $connection = $model->getConnection();

        if ($connection !== (new AffiliateSupportTicket)->getConnection()) {
            throw new AuthorizationException('Support messages require the ticket database connection.');
        }

        $guard = app(AffiliateAccessGuard::class);

        foreach (array_unique($ticketIds) as $ticketId) {
            if (! is_string($ticketId) || ! Str::isUuid($ticketId)) {
                throw new AuthorizationException('A support message requires a valid ticket reference.');
            }

            $ticket = AffiliateSupportTicket::query()->withoutGlobalScope('affiliate_owner')
                ->whereKey($ticketId)->first(['affiliate_id']);

            if ($ticket === null) {
                throw new AuthorizationException('Cross-tenant support ticket reference is not allowed.');
            }

            $guard->assertCanWrite($ticket->affiliate_id, spl_object_id($connection));
        }
    }
}
