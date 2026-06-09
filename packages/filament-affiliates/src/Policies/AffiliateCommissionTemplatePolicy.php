<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Policies;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use Illuminate\Foundation\Auth\User;

final class AffiliateCommissionTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return FilamentPermission::hasAbility('affiliate-commission-template.viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('affiliate-commission-template.view');
    }

    public function create(User $user): bool
    {
        return FilamentPermission::hasAbility('affiliate-commission-template.create');
    }

    public function update(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('affiliate-commission-template.update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return FilamentPermission::hasAbility('affiliate-commission-template.delete');
    }
}
