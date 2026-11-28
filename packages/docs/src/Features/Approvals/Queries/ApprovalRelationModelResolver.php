<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Approvals\Queries;

use AIArmada\Docs\Features\Approvals\Models\DocApproval;
use AIArmada\Docs\Runtime\ApprovalRelationModel;

final readonly class ApprovalRelationModelResolver implements ApprovalRelationModel
{
    public function approval(): string
    {
        return DocApproval::class;
    }
}
