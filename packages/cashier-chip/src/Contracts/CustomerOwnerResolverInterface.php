<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves owner scoping for billable customer models without an owner tuple.
 *
 * Billable models (User, Team) typically carry no owner_type/owner_id columns,
 * so the standard owner-column scope cannot apply. Implementations prove
 * ownership via self-identity, owner columns when present, or owned relations
 * such as the CHIP customer link and subscriptions.
 *
 * The default implementation is used unless
 * `cashier-chip.features.owner.customer_resolver` points to a custom class.
 */
interface CustomerOwnerResolverInterface
{
    /**
     * Constrain a customer list query to the given owner.
     *
     * A null owner means explicit global context: models with an owner tuple
     * narrow to global-only rows, while models without one return the query
     * unfiltered. A resolved owner must narrow the query to provably-owned
     * records, failing closed (no rows) when ownership cannot be proven.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function scopeQuery(Builder $query, ?Model $owner): Builder;

    /**
     * Check whether a single customer record is accessible to the given owner.
     *
     * A null owner means explicit global context: true for global rows on
     * tuple models and for all rows on models without an owner tuple. A
     * resolved owner must return true only when ownership can be proven.
     */
    public function canAccess(Model $record, ?Model $owner): bool;
}
