<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Payouts;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliatePayoutOperation;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Affiliates\States\ProcessingPayout;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

final class ClaimScheduledPayout
{
    use AsAction;

    public function isEligibleSnapshot(string $affiliateId, int $minimumAmountMinor): bool
    {
        $affiliate = Affiliate::query()->forOwner()->find($affiliateId);

        if (! $affiliate instanceof Affiliate) {
            return false;
        }

        $balance = AffiliateBalance::query()->where('affiliate_id', $affiliate->id)->first();

        if (! $balance instanceof AffiliateBalance) {
            return false;
        }

        $threshold = max($minimumAmountMinor, $balance->minimum_payout_minor);

        if ($balance->available_minor < $threshold || $balance->available_minor <= 0) {
            return false;
        }

        if ($affiliate->payoutHolds()
            ->whereNull('released_at')
            ->where(static function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists()) {
            return false;
        }

        if ($affiliate->payouts()
            ->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()])
            ->exists()) {
            return false;
        }

        $allocation = $this->fundedConversionAllocation($affiliate, $balance->available_minor);

        return $allocation['amount_minor'] >= $threshold;
    }

    public function handle(string $affiliateId, int $minimumAmountMinor): ?AffiliatePayoutOperation
    {
        return DB::transaction(function () use ($affiliateId, $minimumAmountMinor): ?AffiliatePayoutOperation {
            $affiliate = Affiliate::query()->forOwner()->lockForUpdate()->find($affiliateId);

            if (! $affiliate instanceof Affiliate) {
                return null;
            }

            $balance = AffiliateBalance::query()
                ->where('affiliate_id', $affiliate->id)
                ->lockForUpdate()
                ->first();

            if (! $balance instanceof AffiliateBalance) {
                return null;
            }

            $threshold = max($minimumAmountMinor, $balance->minimum_payout_minor);

            if ($balance->available_minor < $threshold || $balance->available_minor <= 0) {
                return null;
            }

            $activeHold = $affiliate->payoutHolds()
                ->whereNull('released_at')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->first();

            if ($activeHold !== null) {
                return null;
            }

            $pending = $affiliate->payouts()
                ->whereIn('status', [PendingPayout::value(), ProcessingPayout::value()])
                ->lockForUpdate()
                ->first();

            if ($pending !== null) {
                return null;
            }

            $allocation = $this->fundedConversionAllocation(
                affiliate: $affiliate,
                availableMinor: $balance->available_minor,
                lockForUpdate: true,
            );
            $conversionIds = $allocation['conversion_ids'];
            $amountMinor = $allocation['amount_minor'];

            if ($conversionIds === [] || $amountMinor < $threshold) {
                return null;
            }

            $sequence = $balance->payout_sequence + 1;
            $operation = AffiliatePayoutOperation::query()->create([
                'affiliate_id' => $affiliate->id,
                'operation_key' => sprintf('scheduled:%s:%d', $affiliate->id, $sequence),
                'status' => 'claimed',
                'amount_minor' => $amountMinor,
                'currency' => mb_strtoupper($balance->currency),
                'payout_sequence' => $sequence,
                'claimed_at' => now(),
                'lease_expires_at' => now()->addMinutes(5),
                'owner_type' => $affiliate->owner_type,
                'owner_id' => $affiliate->owner_id,
            ]);

            $payout = AffiliatePayout::query()->create([
                'affiliate_payout_operation_id' => $operation->id,
                'reference' => 'PAY-' . mb_strtoupper(str_replace('-', '', $operation->id)),
                'payee_type' => $affiliate->getMorphClass(),
                'payee_id' => $affiliate->id,
                'owner_type' => $affiliate->owner_type,
                'owner_id' => $affiliate->owner_id,
                'total_minor' => $amountMinor,
                'conversion_count' => count($conversionIds),
                'currency' => mb_strtoupper($balance->currency),
                'status' => PendingPayout::value(),
                'scheduled_at' => now(),
            ]);

            $balance->forceFill([
                'available_minor' => $balance->available_minor - $amountMinor,
                'payout_sequence' => $sequence,
            ])->save();

            $affiliate->conversions()
                ->whereIn('id', $conversionIds)
                ->whereNull('affiliate_payout_id')
                ->update(['affiliate_payout_id' => $payout->id]);

            $payout->events()->create([
                'to_status' => PendingPayout::value(),
                'notes' => 'Payout reserved by atomic scheduled operation ' . $operation->id,
            ]);

            $operation->forceFill([
                'affiliate_payout_id' => $payout->id,
                'status' => 'reserved',
            ])->save();

            return $operation->refresh();
        }, attempts: 3);
    }

    /**
     * Allocate a deterministic FIFO prefix of fully funded conversions.
     *
     * @return array{conversion_ids:list<string>,amount_minor:int}
     */
    private function fundedConversionAllocation(
        Affiliate $affiliate,
        int $availableMinor,
        bool $lockForUpdate = false,
    ): array {
        if ($availableMinor <= 0) {
            return ['conversion_ids' => [], 'amount_minor' => 0];
        }

        $query = $affiliate->conversions()
            ->where('status', ApprovedConversion::value())
            ->whereNull('affiliate_payout_id')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->select(['id', 'commission_minor']);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $conversionIds = [];
        $amountMinor = 0;

        foreach ($query->get() as $conversion) {
            $commissionMinor = max(0, (int) $conversion->commission_minor);

            if ($commissionMinor === 0) {
                continue;
            }

            if ($amountMinor + $commissionMinor > $availableMinor) {
                break;
            }

            $conversionIds[] = (string) $conversion->id;
            $amountMinor += $commissionMinor;
        }

        return [
            'conversion_ids' => $conversionIds,
            'amount_minor' => $amountMinor,
        ];
    }
}
