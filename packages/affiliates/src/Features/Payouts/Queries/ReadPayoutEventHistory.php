<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Payouts\Queries;

use AIArmada\Affiliates\Features\Payouts\Api\PayoutEventHistory;
use AIArmada\Affiliates\Features\Payouts\Api\PayoutEventRecord;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayoutEvent;
use AIArmada\Affiliates\Models\AffiliatePayout;
use Illuminate\Support\Str;
use LogicException;

final class ReadPayoutEventHistory implements PayoutEventHistory
{
    public function hasEvents(string $payoutId): bool
    {
        $this->assertSharedConnection();

        return AffiliatePayoutEvent::query()->where('affiliate_payout_id', $payoutId)->exists();
    }

    public function forPayout(string $payoutId, int $limit = 100): array
    {
        $this->assertSharedConnection();

        if (! Str::isUuid($payoutId) || ! AffiliatePayout::query()->forOwner()->whereKey($payoutId)->exists()) {
            return [];
        }

        return AffiliatePayoutEvent::query()
            ->where('affiliate_payout_id', $payoutId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, min(500, $limit)))
            ->get()
            ->map(static fn (AffiliatePayoutEvent $event): PayoutEventRecord => new PayoutEventRecord(
                $event->id,
                $event->from_status,
                $event->to_status,
                $event->notes,
                $event->created_at?->toIso8601String(),
            ))
            ->values()
            ->all();
    }

    private function assertSharedConnection(): void
    {
        if ((new AffiliatePayout)->getConnection() !== (new AffiliatePayoutEvent)->getConnection()) {
            throw new LogicException('Payout event history requires a shared database connection.');
        }
    }
}
