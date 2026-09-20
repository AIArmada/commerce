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
use AIArmada\Affiliates\Support\PayoutMinimums;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsAction;

final class ApplyConversionAccounting
{
    use AsAction;

    /** @var array<string, bool> */
    private static array $balancesTableMemo = [];

    public static function balancesSyncEnabled(): bool
    {
        $table = (new AffiliateBalance)->getTable();

        return self::$balancesTableMemo[$table] ??= Schema::hasTable($table);
    }

    public function handle(AffiliateConversion $conversion, ?ConversionStatus $previousStatus = null): void
    {
        if (! self::balancesSyncEnabled()) {
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

            $currency = mb_strtoupper((string) ($conversion->commission_currency ?: $lockedAffiliate->currency ?: config('affiliates.currency.default', 'MYR')));

            $balance = $lockedAffiliate->balances()->where('currency', $currency)->lockForUpdate()->first()
                ?? self::createBalance($lockedAffiliate, $currency);

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
                $balance->voidFromHolding($conversion->commission_minor);
            }
        }

        if ($previousStatus->equals(ApprovedConversion::class) && $newStatus->equals(RejectedConversion::class)) {
            $balance->voidFromAvailable($conversion->commission_minor);
        }

        if ($newStatus->equals(PaidConversion::class) && $conversion->affiliate_payout_id === null) {
            $balance->deductFromAvailable($conversion->commission_minor);
        }
    }

    private function resolveStatus(AffiliateConversion $conversion): ConversionStatus
    {
        return ConversionStatus::fromString($conversion->status, $conversion);
    }

    private static function createBalance(Affiliate $affiliate, string $currency): AffiliateBalance
    {
        try {
            return AffiliateBalance::create([
                'affiliate_id' => $affiliate->id,
                'available_minor' => 0,
                'holding_minor' => 0,
                'lifetime_earnings_minor' => 0,
                'minimum_payout_minor' => PayoutMinimums::forCurrency($currency),
                'currency' => $currency,
            ]);
        } catch (QueryException $exception) {
            // Concurrent first conversion in this currency: the unique
            // (affiliate_id, currency) row already exists, so reuse it.
            if (! in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true)) {
                throw $exception;
            }

            return $affiliate->balances()->where('currency', $currency)->firstOrFail();
        }
    }
}
