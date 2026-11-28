<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance;

use AIArmada\Events\Contracts\EventApprovalWorkflow;
use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Contracts\EventSubmissionConverter;
use AIArmada\Events\Features\Governance\Actions\DefaultEventApprovalWorkflow;
use AIArmada\Events\Features\Governance\Actions\DefaultEventModerationWorkflow;
use AIArmada\Events\Features\Governance\Actions\DefaultEventSubmissionConverter;
use AIArmada\Events\Features\Governance\Api\GovernanceApi;
use AIArmada\Events\Features\Governance\Models\EventApprovalRequest;
use AIArmada\Events\Features\Governance\Models\EventEscalation;
use AIArmada\Events\Features\Governance\Models\EventManagementAssignment;
use AIArmada\Events\Features\Governance\Models\EventSubmission;
use AIArmada\Events\Features\Governance\Policies\EventSubmissionPolicy;
use AIArmada\Events\Features\Governance\Queries\EloquentGovernanceApi;
use AIArmada\Events\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Gate;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $this->defaultModelConfig('submission', EventSubmission::class);
        $this->defaultModelConfig('escalation', EventEscalation::class);
        $this->defaultModelConfig('approval_request', EventApprovalRequest::class);
        $this->defaultModelConfig('management_assignment', EventManagementAssignment::class);

        $app->bind(EventApprovalWorkflow::class, DefaultEventApprovalWorkflow::class);
        $app->singleton(EventModerationWorkflow::class, DefaultEventModerationWorkflow::class);
        $app->bind(EventSubmissionConverter::class, DefaultEventSubmissionConverter::class);
        $app->bind(GovernanceApi::class, EloquentGovernanceApi::class);
    }

    public function boot(Application $app): void
    {
        Gate::policy(ModelResolver::submissionClass(), EventSubmissionPolicy::class);
    }

    /**
     * @param  class-string  $model
     */
    private function defaultModelConfig(string $key, string $model): void
    {
        if (config('events.models.' . $key) !== null) {
            return;
        }

        config(['events.models.' . $key => $model]);
    }
}
