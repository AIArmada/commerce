<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\SuppressionResolver;
use AIArmada\Communications\Data\SuppressionDecisionData;

class NullSuppressionResolver implements SuppressionResolver
{
    public function resolve(
        ?string $recipientType,
        ?string $recipientId,
        ?string $destinationHash,
        ?string $channel,
        ?string $category,
    ): SuppressionDecisionData {
        return SuppressionDecisionData::from(['suppressed' => false]);
    }
}
