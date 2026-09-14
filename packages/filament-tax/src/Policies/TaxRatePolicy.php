<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Policies;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Tax\Models\TaxRate;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class TaxRatePolicy
{
    public function viewAny(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.rates.view')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxRate::class);
    }

    public function view(Authorizable $user, TaxRate $rate): bool
    {
        return self::hasPermission($user, 'tax.rates.view')
            && OwnerUiScope::canAccessRecord($rate);
    }

    public function create(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.rates.create')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxRate::class);
    }

    public function update(Authorizable $user, TaxRate $rate): bool
    {
        return self::hasPermission($user, 'tax.rates.update')
            && OwnerUiScope::canMutateRecord($rate);
    }

    public function delete(Authorizable $user, TaxRate $rate): bool
    {
        return self::hasPermission($user, 'tax.rates.delete')
            && OwnerUiScope::canMutateRecord($rate);
    }

    public function deleteAny(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.rates.delete')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxRate::class);
    }

    private static function hasPermission(Authorizable $user, string $permission): bool
    {
        // Gate abilities are the package convention (table/page actions, settings
        // page, and HasPageAuthz all check `$user->can('tax.*')`). Spatie's
        // hasPermissionTo() throws for unseeded permissions instead of denying.
        return $user->can($permission);
    }
}
