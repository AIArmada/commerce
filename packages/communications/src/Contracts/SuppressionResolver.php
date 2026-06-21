<?php

declare(strict_types=1);

namespace AIArmada\Communications\Contracts;

use AIArmada\Communications\Data\SuppressionDecisionData;

interface SuppressionResolver
{
    public function resolve(
        ?string $recipientType,
        ?string $recipientId,
        ?string $destinationHash,
        ?string $channel,
        ?string $category,
    ): SuppressionDecisionData;
}
