<?php

declare(strict_types=1);

namespace AIArmada\Communications\Services;

use AIArmada\Communications\Contracts\ConsentResolver;
use AIArmada\Communications\Data\ConsentDecisionData;
use AIArmada\Communications\Enums\CommunicationCategory;

class NullConsentResolver implements ConsentResolver
{
    public function resolve(
        ?string $recipientType,
        ?string $recipientId,
        string $channel,
        string $category,
    ): ConsentDecisionData {
        if ($category === CommunicationCategory::Marketing->value) {
            return ConsentDecisionData::from(['consented' => false, 'reason' => 'Marketing requires explicit consent; no resolver configured.']);
        }

        return ConsentDecisionData::from(['consented' => true]);
    }
}
