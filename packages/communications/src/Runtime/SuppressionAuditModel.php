<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

use AIArmada\Communications\Features\Suppressions\Models\CommunicationSuppression;

/** Internal Eloquent relation and audit composition. */
interface SuppressionAuditModel
{
    /** @return class-string<CommunicationSuppression> */
    public function suppression(): string;
}
