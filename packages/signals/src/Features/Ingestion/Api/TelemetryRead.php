<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Ingestion\Api;

use Carbon\CarbonImmutable;

interface TelemetryRead
{
    /** @return array<string, string> */
    public function propertyOptions(): array;

    public function propertyCurrency(string $id): ?string;

    public function requirePropertyCurrency(string $id): string;

    public function propertyCurrencyForWrite(string $id): string;

    /** @return array{tracked_properties:int,identities:int} */
    public function counts(?string $propertyId, CarbonImmutable $startAt, CarbonImmutable $endAt): array;
}
