<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Policies;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Features\Forms\Models\FeedbackForm;
use Illuminate\Database\Eloquent\Model;

final class FeedbackFormPolicy
{
    public function viewAny(Model $user): bool
    {
        return $this->canOperate();
    }

    public function view(Model $user, FeedbackForm $form): bool
    {
        return $this->ownerMatches($form);
    }

    public function create(Model $user): bool
    {
        return $this->canOperate();
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
        return $this->canOperate();
    }

    private function canOperate(): bool
    {
        $config = FeedbackForm::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        return $this->resolveOwner($config) !== null || OwnerContext::isExplicitGlobal();
    }

    private function ownerMatches(FeedbackForm $form): bool
    {
        $config = FeedbackForm::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        $owner = $this->resolveOwner($config);

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $form->isGlobal();
        }

        return $form->belongsToOwner($owner);
    }

    private function resolveOwner(OwnerScopeConfig $config): ?Model
    {
        return OwnerContext::resolveConfigured($config);
    }
}
