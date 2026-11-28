<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Approvals;

use AIArmada\Docs\Contracts\DocumentApprovalRequests;
use AIArmada\Docs\Contracts\DocumentApprovals;
use AIArmada\Docs\Contracts\DocumentWorkflows;
use AIArmada\Docs\Features\Approvals\Actions\DecideDocApproval;
use AIArmada\Docs\Features\Approvals\Actions\ManageDocApprovalRequests;
use AIArmada\Docs\Features\Approvals\Actions\ManageDocWorkflows;
use AIArmada\Docs\Features\Approvals\Queries\ApprovalRelationModelResolver;
use AIArmada\Docs\Platform\FeatureModule as FeatureModuleContract;
use AIArmada\Docs\Runtime\ApprovalRelationModel;
use Illuminate\Contracts\Foundation\Application;

final class FeatureModule implements FeatureModuleContract
{
    public function register(Application $app): void
    {
        $app->bind(DocumentApprovals::class, DecideDocApproval::class);
        $app->bind(DocumentApprovalRequests::class, ManageDocApprovalRequests::class);
        $app->bind(DocumentWorkflows::class, ManageDocWorkflows::class);
        $app->bind(ApprovalRelationModel::class, ApprovalRelationModelResolver::class);
    }

    public function boot(Application $app): void {}
}
