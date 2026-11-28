<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Delivery\Api;

use AIArmada\Communications\Data\ProviderEventData;

interface ProviderEventProcessor
{
    public function process(ProviderEventData $eventData): void;
}
