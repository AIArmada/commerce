<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Policies;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class TaxZonePolicy
{
    public function viewAny(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.zones.view')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxZone::class);
    }

    public function view(Authorizable $user, TaxZone $zone): bool
    {
        return self::hasPermission($user, 'tax.zones.view')
            && OwnerUiScope::canAccessRecord($zone);
    }

    public function create(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.zones.create')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxZone::class);
    }

    public function update(Authorizable $user, TaxZone $zone): bool
    {
        return self::hasPermission($user, 'tax.zones.update')
            && OwnerUiScope::canMutateRecord($zone);
    }

    public function delete(Authorizable $user, TaxZone $zone): bool
    {
        return self::hasPermission($user, 'tax.zones.delete')
            && OwnerUiScope::canMutateRecord($zone);
    }

    public function deleteAny(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.zones.delete')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxZone::class);
    }

    private static function hasPermission(Authorizable $user, string $permission): bool
    {
        // Gate abilities are the package convention (table/page actions, settings
        // page, and HasPageAuthz all check `$user->can('tax.*')`). Spatie's
        // hasPermissionTo() throws for unseeded permissions instead of denying.
        return $user->can($permission);
    }
}
