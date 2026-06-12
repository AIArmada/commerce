<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Models\FeedbackTemplate;
use Illuminate\Database\Eloquent\Model;

final class FeedbackTemplatePolicy
{
    public function viewAny(Model $user): bool
    {
        return OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal();
    }

    public function view(Model $user, FeedbackTemplate $template): bool
    {
        return $this->ownerMatches($template);
    }

    public function create(Model $user): bool
    {
        return OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal();
    }

    public function update(Model $user, FeedbackTemplate $template): bool
    {
        return $this->ownerMatches($template);
    }

    public function delete(Model $user, FeedbackTemplate $template): bool
    {
        return $this->ownerMatches($template);
    }

    public function export(Model $user): bool
    {
        return OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal();
    }

    private function ownerMatches(FeedbackTemplate $template): bool
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $template->isGlobal();
        }

        return $template->belongsToOwner($owner);
    }
}
