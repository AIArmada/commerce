<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Api;

use AIArmada\Affiliates\Data\PayoutResult;

interface PayoutAdministration
{
    /** @param array<int, string> $conversionIds
     * @param array<string, mixed> $attributes
     */
    public function create(string $affiliateId, array $conversionIds, array $attributes = []): string;

    public function process(string $payoutId): PayoutResult;

    public function updateStatus(string $payoutId, string $status, ?string $notes = null, bool $retainNote = false): void;
}
