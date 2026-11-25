<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface ConversionAdministration
{
    public function transition(string $conversionId, string $status): bool;

    public function reverse(string $conversionId, string $reason): void;

    public function voidConversion(string $conversionId, string $reason): void;
}
