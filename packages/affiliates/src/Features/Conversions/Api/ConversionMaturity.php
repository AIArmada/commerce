<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface ConversionMaturity
{
    public function mature(string $conversionId): bool;

    public function process(bool $dryRun = false): int;
}
