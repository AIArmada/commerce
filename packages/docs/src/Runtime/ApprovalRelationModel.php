<?php

declare(strict_types=1);

namespace AIArmada\Docs\Runtime;

/** @phpstan-type RelatedDocApproval \AIArmada\Docs\Features\Approvals\Models\DocApproval */
interface ApprovalRelationModel
{
    /** @return class-string<RelatedDocApproval> */
    public function approval(): string;
}
