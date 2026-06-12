<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Policies;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Models\FeedbackForm;
use Illuminate\Database\Eloquent\Model;

final class FeedbackFormPolicy
{
    public function viewAny(Model $user): bool
    {
        return OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal();
    }

    public function view(Model $user, FeedbackForm $form): bool
    {
        return $this->ownerMatches($form);
    }

    public function create(Model $user): bool
    {
        return OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal();
    }

    public function update(Model $user, FeedbackForm $form): bool
    {
        return $this->ownerMatches($form);
    }

    public function delete(Model $user, FeedbackForm $form): bool
    {
        return $this->ownerMatches($form);
    }

    public function publish(Model $user, FeedbackForm $form): bool
    {
        return $this->ownerMatches($form);
    }

    public function close(Model $user, FeedbackForm $form): bool
    {
        return $this->ownerMatches($form);
    }

    public function archive(Model $user, FeedbackForm $form): bool
    {
        return $this->ownerMatches($form);
    }

    public function submit(Model $user): bool
    {
        return true;
    }

    public function export(Model $user): bool
    {
        return OwnerContext::resolve() !== null || OwnerContext::isExplicitGlobal();
    }

    private function ownerMatches(FeedbackForm $form): bool
    {
        $owner = OwnerContext::resolve();

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $form->isGlobal();
        }

        return $form->belongsToOwner($owner);
    }
}
