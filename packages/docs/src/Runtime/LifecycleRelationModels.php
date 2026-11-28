<?php

declare(strict_types=1);

namespace AIArmada\Docs\Runtime;

/**
 * @phpstan-type RelatedDocStatusHistory \AIArmada\Docs\Features\Lifecycle\Models\DocStatusHistory
 * @phpstan-type RelatedDocEInvoiceSubmission \AIArmada\Docs\Features\Lifecycle\Models\DocEInvoiceSubmission
 */
interface LifecycleRelationModels
{
    /** @return class-string<RelatedDocStatusHistory> */
    public function statusHistory(): string;

    /** @return class-string<RelatedDocEInvoiceSubmission> */
    public function eInvoiceSubmission(): string;
}
