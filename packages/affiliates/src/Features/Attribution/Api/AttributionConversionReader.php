<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Attribution\Api;

interface AttributionConversionReader
{
    public function active(?string $attributionId, ?string $cartIdentifier, string $cartInstance, int $connectionIdentity): ?AttributionConversionRecord;

    public function frozen(string $id, int $connectionIdentity): AttributionConversionRecord;

    public function forOutcome(string $id, int $connectionIdentity): AttributionConversionRecord;

    public function evidence(string $id, ?string $ownerType, ?string $ownerId, int $connectionIdentity): ?AttributionEvidence;
}
