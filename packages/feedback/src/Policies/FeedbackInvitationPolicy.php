<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Models\FeedbackInvitation;
use Illuminate\Database\Eloquent\Model;

final class FeedbackInvitationPolicy
{
    public function viewAny(Model $user): bool
    {
        return OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal();
    }

    public function view(Model $user, FeedbackInvitation $invitation): bool
    {
        return $this->ownerMatches($invitation);
    }

    public function create(Model $user): bool
    {
        return OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal();
    }

    public function send(Model $user, FeedbackInvitation $invitation): bool
    {
        return $this->ownerMatches($invitation);
    }

    public function cancel(Model $user, FeedbackInvitation $invitation): bool
    {
        return $this->ownerMatches($invitation);
    }

    private function ownerMatches(FeedbackInvitation $invitation): bool
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $invitation->isGlobal();
        }

        return $invitation->belongsToOwner($owner);
    }
}
