<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Invitations\Policies;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Features\Invitations\Models\FeedbackInvitation;
use Illuminate\Database\Eloquent\Model;

final class FeedbackInvitationPolicy
{
    public function viewAny(Model $user): bool
    {
        return $this->canOperate();
    }

    public function view(Model $user, FeedbackInvitation $invitation): bool
    {
        return $this->ownerMatches($invitation);
    }

    public function create(Model $user): bool
    {
        return $this->canOperate();
    }

    public function send(Model $user, FeedbackInvitation $invitation): bool
    {
        return $this->ownerMatches($invitation);
    }

    public function cancel(Model $user, FeedbackInvitation $invitation): bool
    {
        return $this->ownerMatches($invitation);
    }

    private function canOperate(): bool
    {
        $config = FeedbackInvitation::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        return $this->resolveOwner($config) !== null || OwnerContext::isExplicitGlobal();
    }

    private function ownerMatches(FeedbackInvitation $invitation): bool
    {
        $config = FeedbackInvitation::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        $owner = $this->resolveOwner($config);

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $invitation->isGlobal();
        }

        return $invitation->belongsToOwner($owner);
    }

    private function resolveOwner(OwnerScopeConfig $config): ?Model
    {
        return OwnerContext::resolveConfigured($config);
    }
}
