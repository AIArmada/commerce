<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Policies;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Tax\Models\TaxExemption;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class TaxExemptionPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.exemptions.view')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxExemption::class);
    }

    public function view(Authorizable $user, TaxExemption $exemption): bool
    {
        return self::hasPermission($user, 'tax.exemptions.view')
            && OwnerUiScope::canAccessRecord($exemption);
    }

    public function create(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.exemptions.create')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxExemption::class);
    }

    public function update(Authorizable $user, TaxExemption $exemption): bool
    {
        return self::hasPermission($user, 'tax.exemptions.update')
            && OwnerUiScope::canMutateRecord($exemption);
    }

    public function delete(Authorizable $user, TaxExemption $exemption): bool
    {
        return self::hasPermission($user, 'tax.exemptions.delete')
            && OwnerUiScope::canMutateRecord($exemption);
    }

    public function deleteAny(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.exemptions.delete')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxExemption::class);
    }

    private static function hasPermission(Authorizable $user, string $permission): bool
    {
        // Gate abilities are the package convention (table/page actions, settings
        // page, and HasPageAuthz all check `$user->can('tax.*')`). Spatie's
        // hasPermissionTo() throws for unseeded permissions instead of denying.
        return $user->can($permission);
    }
}
