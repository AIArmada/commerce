<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface ConversionInspectionReader
{
    public function find(string $conversionId): ?ConversionInspection;

    public function assertConnection(int $connectionIdentity): void;

    public function lock(string $conversionId, int $connectionIdentity): ?ConversionInspection;
}
