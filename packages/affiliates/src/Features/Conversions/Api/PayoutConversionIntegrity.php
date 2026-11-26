<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Conversions\Api;

interface PayoutConversionIntegrity
{
    /**
     * @param  array<string, array{owner_type: ?string, owner_id: ?string}>  $payoutOwners
     * @return array<string, array{total_minor: int, currencies: list<string>, count: int}>
     */
    public function reportTotals(array $payoutOwners, int $connectionIdentity): array;

    /** @return iterable<int, PayoutExportRow> */
    public function exportRows(string $payoutId, ?string $ownerType, ?string $ownerId, int $connectionIdentity): iterable;

    public function hasConversions(string $payoutId, int $connectionIdentity): bool;

    /** @return list<string> */
    public function affiliateIds(string $payoutId, int $connectionIdentity, ?string $exceptConversionId = null): array;

    public function summary(string $payoutId, ?string $ownerType, ?string $ownerId, int $connectionIdentity): PayoutConversionSummary;

    /** @return list<string> */
    public function currencies(string $payoutId, int $connectionIdentity): array;
}
