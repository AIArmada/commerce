<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Drivers\Payouts;

use AIArmada\Affiliates\Contracts\PayoutProcessorInterface;
use AIArmada\Affiliates\Data\PayoutRequest;
use AIArmada\Affiliates\Data\PayoutResult;
use AIArmada\Affiliates\Data\PayoutStatusResult;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Manual payout processor for bank transfers, checks, etc.
 */
final class ManualPayoutProcessor implements PayoutProcessorInterface
{
    public function process(PayoutRequest $payout): PayoutResult
    {
        return PayoutResult::pending(
            externalReference: 'MANUAL-' . Str::upper(Str::random(12)),
            metadata: [
                'type' => 'manual',
                'requires_admin_action' => true,
            ]
        );
    }

    public function getStatus(PayoutRequest $payout): PayoutStatusResult
    {
        return new PayoutStatusResult($payout->status, $payout->externalReference);
    }

    public function cancel(PayoutRequest $payout): bool
    {
        return true;
    }

    public function getEstimatedArrival(PayoutRequest $payout): ?DateTimeInterface
    {
        return CarbonImmutable::now()->addDays(5);
    }

    public function getFees(int $amountMinor, string $currency): int
    {
        return 0;
    }

    public function validateDetails(array $details): array
    {
        return [];
    }

    public function getIdentifier(): string
    {
        return 'manual';
    }
}
