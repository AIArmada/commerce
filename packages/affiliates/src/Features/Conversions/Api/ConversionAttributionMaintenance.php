<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface ConversionAttributionMaintenance
{
    public function detachAttribution(string $attributionId): void;
}
