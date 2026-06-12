<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Model;

final class FeedbackResponsePolicy
{
    public function viewAny(Model $user): bool
    {
        return OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal();
    }

    public function view(Model $user, FeedbackResponse $response): bool
    {
        return $this->ownerMatches($response);
    }

    public function review(Model $user, FeedbackResponse $response): bool
    {
        return $this->ownerMatches($response);
    }

    public function reject(Model $user, FeedbackResponse $response): bool
    {
        return $this->ownerMatches($response);
    }

    public function markSpam(Model $user, FeedbackResponse $response): bool
    {
        return $this->ownerMatches($response);
    }

    public function export(Model $user): bool
    {
        return OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal();
    }

    private function ownerMatches(FeedbackResponse $response): bool
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $response->isGlobal();
        }

        return $response->belongsToOwner($owner);
    }
}
