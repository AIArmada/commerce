<?php

declare(strict_types=1);

namespace AIArmada\Communications\Runtime;

/** Internal Eloquent relation and audit composition. */
interface SuppressionAuditModel
{
    /** @return class-string<\AIArmada\Communications\Features\Suppressions\Models\CommunicationSuppression> */
    public function suppression(): string;
}
