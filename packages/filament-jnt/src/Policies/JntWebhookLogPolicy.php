<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Policies;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Jnt\Models\JntWebhookLog;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class JntWebhookLogPolicy
{
    public function viewAny(Authorizable $user): bool
    {
        return OwnerUiScope::canCreate(JntWebhookLog::class);
    }

    public function view(Authorizable $user, JntWebhookLog $log): bool
    {
        return OwnerUiScope::canAccessRecord($log);
    }

    public function create(Authorizable $user): bool
    {
        return OwnerUiScope::canCreate(JntWebhookLog::class);
    }

    public function update(Authorizable $user, JntWebhookLog $log): bool
    {
        return OwnerUiScope::canMutateRecord($log);
    }

    public function delete(Authorizable $user, JntWebhookLog $log): bool
    {
        return OwnerUiScope::canMutateRecord($log);
    }

    public function deleteAny(Authorizable $user): bool
    {
        return OwnerUiScope::canCreate(JntWebhookLog::class);
    }

    public function restore(Authorizable $user, JntWebhookLog $log): bool
    {
        return OwnerUiScope::canMutateRecord($log);
    }

    public function forceDelete(Authorizable $user, JntWebhookLog $log): bool
    {
        return OwnerUiScope::canMutateRecord($log);
    }
}
