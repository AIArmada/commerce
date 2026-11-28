<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Lifecycle\Queries;

use AIArmada\Docs\Features\Lifecycle\Models\DocEInvoiceSubmission;
use AIArmada\Docs\Features\Lifecycle\Models\DocStatusHistory;
use AIArmada\Docs\Runtime\LifecycleRelationModels;

final readonly class LifecycleRelationModelsResolver implements LifecycleRelationModels
{
    public function statusHistory(): string
    {
        return DocStatusHistory::class;
    }

    public function eInvoiceSubmission(): string
    {
        return DocEInvoiceSubmission::class;
    }
}
