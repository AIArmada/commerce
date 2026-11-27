<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Suppressions\Queries;

use AIArmada\Communications\Features\Suppressions\Models\CommunicationSuppression;
use AIArmada\Communications\Runtime\SuppressionAuditModel;

final readonly class SuppressionAuditModelResolver implements SuppressionAuditModel
{
    public function suppression(): string
    {
        return CommunicationSuppression::class;
    }
}
