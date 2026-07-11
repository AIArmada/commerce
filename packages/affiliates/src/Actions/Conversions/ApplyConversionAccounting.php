<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Actions\Conversions;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\QualifiedConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsAction;

final class ApplyConversionAccounting
{
    use AsAction;

    public function handle(AffiliateConversion $conversion, ?ConversionStatus $previousStatus = null): void
    {
        if (! self::syncsAffiliateBalances()) {
            return;
        }

        $affiliate = $conversion->affiliate()->first();

        if (! $affiliate) {
            return;
        }

        DB::transaction(function () use ($affiliate, $conversion, $previousStatus): void {
            $lockedAffiliate = Affiliate::query()
                ->whereKey($affiliate->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $balance = $lockedAffiliate->balance()->first() ?? self::createBalance($lockedAffiliate, $conversion);

            if ($previousStatus === null) {
                $this->applyCreationAccounting($conversion, $balance);
            } else {
                $this->applyTransitionAccounting($conversion, $balance, $previousStatus);
            }
        });
    }

    private function applyCreationAccounting(AffiliateConversion $conversion, AffiliateBalance $balance): void
    {
        $status = $this->resolveStatus($conversion);

        if ($status->equals(ApprovedConversion::class)) {
            $balance->increment('available_minor', $conversion->commission_minor);
            $balance->increment('lifetime_earnings_minor', $conversion->commission_minor);

            return;
        }

        if ($status->equals(PendingConversion::class) || $status->equals(QualifiedConversion::class)) {
            $balance->addToHolding($conversion->commission_minor);

            return;
        }

        if ($status->equals(PaidConversion::class)) {
            $balance->increment('lifetime_earnings_minor', $conversion->commission_minor);
        }
    }

    private function applyTransitionAccounting(AffiliateConversion $conversion, AffiliateBalance $balance, ConversionStatus $previousStatus): void
    {
        $newStatus = $this->resolveStatus($conversion);

        if ($previousStatus->equals(QualifiedConversion::class) || $previousStatus->equals(PendingConversion::class)) {
            if ($newStatus->equals(ApprovedConversion::class)) {
                $holdingMinor = $balance->holding_minor;
                $balance->releaseFromHolding($conversion->commission_minor);

                $remainingMinor = max(0, $conversion->commission_minor - $holdingMinor);

                if ($remainingMinor > 0) {
                    $balance->increment('available_minor', $remainingMinor);
                }
            }

            if ($newStatus->equals(RejectedConversion::class)) {
                $balance->decrement('holding_minor', $conversion->commission_minor);
                $balance->decrement('lifetime_earnings_minor', $conversion->commission_minor);
            }
        }

        if ($newStatus->equals(PaidConversion::class)) {
            $balance->deductFromAvailable($conversion->commission_minor);
        }
    }

    private function resolveStatus(AffiliateConversion $conversion): ConversionStatus
    {
        return ConversionStatus::fromString($conversion->status, $conversion);
    }

    private static function createBalance(Affiliate $affiliate, AffiliateConversion $conversion): AffiliateBalance
    {
        return AffiliateBalance::create([
            'affiliate_id' => $affiliate->id,
            'available_minor' => 0,
            'holding_minor' => 0,
            'lifetime_earnings_minor' => 0,
            'minimum_payout_minor' => config('affiliates.payouts.minimum_amount', 5000),
            'currency' => $conversion->commission_currency ?: $affiliate->currency ?: 'MYR',
        ]);
    }

    private static function syncsAffiliateBalances(): bool
    {
        return Schema::hasTable((new AffiliateBalance)->getTable());
    }
}
