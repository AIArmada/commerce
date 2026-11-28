<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Responses\Policies;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Features\Responses\Models\FeedbackResponse;
use Illuminate\Database\Eloquent\Model;

final class FeedbackResponsePolicy
{
    public function viewAny(Model $user): bool
    {
        return $this->canOperate();
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
        return $this->canOperate();
    }

    private function canOperate(): bool
    {
        $config = FeedbackResponse::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        return $this->resolveOwner($config) !== null || OwnerContext::isExplicitGlobal();
    }

    private function ownerMatches(FeedbackResponse $response): bool
    {
        $config = FeedbackResponse::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        $owner = $this->resolveOwner($config);

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $response->isGlobal();
        }

        return $response->belongsToOwner($owner);
    }

    private function resolveOwner(OwnerScopeConfig $config): ?Model
    {
        return OwnerContext::resolveConfigured($config);
    }
}
