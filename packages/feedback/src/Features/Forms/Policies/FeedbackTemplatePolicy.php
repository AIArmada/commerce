<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Forms\Policies;

use AIArmada\CommerceSupport\Data\OwnerScopeConfig;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Feedback\Features\Forms\Models\FeedbackTemplate;
use Illuminate\Database\Eloquent\Model;

final class FeedbackTemplatePolicy
{
    public function viewAny(Model $user): bool
    {
        return $this->canOperate();
    }

    public function view(Model $user, FeedbackTemplate $template): bool
    {
        return $this->ownerMatches(
            $template,
            allowGlobal: (bool) config('feedback.owner.include_global_templates', false),
        );
    }

    public function create(Model $user): bool
    {
        return $this->canOperate();
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
        return $this->canOperate();
    }

    private function canOperate(): bool
    {
        $config = FeedbackTemplate::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        return $this->resolveOwner($config) !== null || OwnerContext::isExplicitGlobal();
    }

    private function ownerMatches(FeedbackTemplate $template, bool $allowGlobal = false): bool
    {
        $config = FeedbackTemplate::ownerScopeConfig();

        if (! $config->enabled) {
            return true;
        }

        $owner = $this->resolveOwner($config);

        if ($owner === null) {
            return OwnerContext::isExplicitGlobal() && $template->isGlobal();
        }

        if ($template->belongsToOwner($owner)) {
            return true;
        }

        return $allowGlobal && $template->isGlobal();
    }

    private function resolveOwner(OwnerScopeConfig $config): ?Model
    {
        return OwnerContext::resolveConfigured($config);
    }
}
