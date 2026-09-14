<?php

declare(strict_types=1);

namespace AIArmada\FilamentTax\Policies;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Tax\Models\TaxClass;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class TaxClassPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.classes.view')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxClass::class);
    }

    public function view(Authorizable $user, TaxClass $class): bool
    {
        return self::hasPermission($user, 'tax.classes.view')
            && OwnerUiScope::canAccessRecord($class);
    }

    public function create(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.classes.create')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxClass::class);
    }

    public function update(Authorizable $user, TaxClass $class): bool
    {
        return self::hasPermission($user, 'tax.classes.update')
            && OwnerUiScope::canMutateRecord($class);
    }

    public function delete(Authorizable $user, TaxClass $class): bool
    {
        return self::hasPermission($user, 'tax.classes.delete')
            && OwnerUiScope::canMutateRecord($class);
    }

    public function deleteAny(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.classes.delete')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxClass::class);
    }

    public function reorder(Authorizable $user): bool
    {
        if (! self::hasPermission($user, 'tax.classes.update')) {
            return false;
        }

        return OwnerUiScope::canCreate(TaxClass::class);
    }

    private static function hasPermission(Authorizable $user, string $permission): bool
    {
        // Gate abilities are the package convention (table/page actions, settings
        // page, and HasPageAuthz all check `$user->can('tax.*')`). Spatie's
        // hasPermissionTo() throws for unseeded permissions instead of denying.
        return $user->can($permission);
    }
}
