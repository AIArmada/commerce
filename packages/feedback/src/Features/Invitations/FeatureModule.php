<?php

declare(strict_types=1);

namespace AIArmada\Feedback\Features\Invitations;

use AIArmada\Feedback\Features\Invitations\Actions\ManageInvitationLifecycleAction;
use AIArmada\Feedback\Features\Invitations\Api\ManagesInvitationLifecycle;
use AIArmada\Feedback\Features\Invitations\Models\FeedbackInvitation;
use AIArmada\Feedback\Features\Invitations\Policies\FeedbackInvitationPolicy;
use AIArmada\Feedback\Platform\FeatureModule as FeatureModuleContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(ManagesInvitationLifecycle::class, ManageInvitationLifecycleAction::class);
        Gate::policy(FeedbackInvitation::class, FeedbackInvitationPolicy::class);
    }

    public function boot(Application $app): void {}
}
