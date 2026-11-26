<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

use AIArmada\Affiliates\Data\AffiliateEventReference;

interface ConversionSignalReader
{
    public function find(AffiliateEventReference $reference): ?ConversionSignalRecord;
}
