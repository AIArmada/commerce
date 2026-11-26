<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

use AIArmada\Affiliates\Data\AffiliateEventReference;

interface AttributionSignalReader
{
    public function find(AffiliateEventReference $reference): ?AttributionSignalRecord;
}
